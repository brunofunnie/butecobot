<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use Monolog\Logger as Monolog;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Command\Command;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event as DiscordEvent;
use ButecoBot\Database\Db;
use ButecoBot\Repository\Event;
use ButecoBot\Repository\EventChoice;
use ButecoBot\Repository\EventBet;
use ButecoBot\Repository\Talk;
use ButecoBot\Repository\UserCoinHistory;
use ButecoBot\Repository\Roulette;
use ButecoBot\Repository\RouletteBet;
use ButecoBot\Repository\User;
use ButecoBot\Repository\UserChangeHistory;
use ButecoBot\Application\Commands\Code\CodeCommand;
use ButecoBot\Application\Commands\Event\AdvertiseCommand;
use ButecoBot\Application\Commands\Event\BetCommand;
use ButecoBot\Application\Commands\Event\CloseCommand;
use ButecoBot\Application\Commands\Event\CreateCommand;
use ButecoBot\Application\Commands\Event\FinishCommand;
use ButecoBot\Application\Commands\Event\ListCommand;
use ButecoBot\Application\Commands\Generic\CoinsCommand;
use ButecoBot\Application\Commands\Generic\TopCeosCommand;
use ButecoBot\Application\Commands\Generic\TransferCommand;
use ButecoBot\Application\Commands\LittleAirplanes\FlyCommand;
use ButecoBot\Application\Commands\Master\AskCommand;
use ButecoBot\Application\Commands\Asking\AskingCommand;
use ButecoBot\Application\Commands\Picasso\PaintCommand;
use ButecoBot\Application\Commands\Roulette\CloseCommand as RouletteCloseCommand;
use ButecoBot\Application\Commands\Roulette\CreateCommand as RouletteCreateCommand;
use ButecoBot\Application\Commands\Roulette\ExposeCommand as RouletteExposeCommand;
use ButecoBot\Application\Commands\Roulette\FinishCommand as RouletteFinishCommand;
use ButecoBot\Application\Commands\Roulette\ListCommand as RouletteListCommand;
use ButecoBot\Application\Commands\Test\TestCommand;
use ButecoBot\Application\Events\MessageCreate;
use ButecoBot\Application\Events\GuildMemberUpdate;
use ButecoBot\Application\Events\VoiceStateUpdate;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['TOKEN']);

// Initialize $config files
$config = [];
$configFiles = glob(__DIR__ . '/config/*.php');

foreach ($configFiles as $file) {
    $fileConfig = include $file;

    if (is_array($fileConfig)) {
        $config = array_merge_recursive($config, $fileConfig);
    }
}

$db = Db::getInstance();

$redis = new RedisClient([
    'scheme' => 'tcp',
    'host' => getenv('REDIS_HOST'),
    'password' => getenv('REDIS_PASSWORD'),
    'port' => 6379,
]);

$logger = new Monolog('ButecoBotCoins');

if (getenv('ENVIRONMENT') === 'production') {
    $rotatingHandler = new RotatingFileHandler(
        __DIR__ . '/../logs/butecobot.log',
        0,
        Level::fromName(getenv('LOG_LEVEL')),
        true,
        0664
    );
    $rotatingHandler->setFilenameFormat('{date}-{filename}', 'Y/m/d');
    $logger->pushHandler($rotatingHandler);
}

$logger->pushHandler(new StreamHandler('php://stdout', Level::fromName(getenv('LOG_LEVEL'))));

$discord = new Discord([
    'token' => getenv('TOKEN'),
    'logger' => $logger,
    'intents' =>
    Intents::getDefaultIntents() |
        Intents::GUILD_MEMBERS |
        Intents::GUILD_PRESENCES |
        Intents::GUILD_MESSAGES |
        Intents::MESSAGE_CONTENT,
    'socket_options' => [
        'dns' => '8.8.8.8',
    ],
]);

$userRepository = new User($db);
$userCoinHistoryRepository = new UserCoinHistory($db);
$userChangeHistoryRepository = new UserChangeHistory($db);
$eventRepository = new Event($db);
$eventChoiceRepository = new EventChoice($db);
$eventBetsRepository = new EventBet($db);
$rouletteRepository = new Roulette($db);
$rouletteBetRepository = new RouletteBet($db);
$talkRepository = new Talk($db);

$discord->on('init', function (Discord $discord) use ($redis, $config) {
    // Initialize application commands
    $initializeCommandsFiles = glob(__DIR__ . '/Application/Initialize/*Command.php');

    foreach ($initializeCommandsFiles as $initializeCommandsFile) {
        $initializeCommand = include $initializeCommandsFile;

        $command = new Command($discord, $initializeCommand);
        $discord->application->commands->save($command);
    }

    $loop = $discord->getLoop();
    $loop->addPeriodicTimer($_ENV['MAIN_LOOP'], function () use ($discord, $config, $redis) {
        if ($_ENV['DRINK_WATER_ENABLE'] == 1) {
            $hourNow = date('G');
            $canDrinkWater = $redis->get('drink_water_notification');

            if ($hourNow < 8 || $hourNow > 20) {
                return;
            }

            if ($canDrinkWater) {
                return;
            }

            $redis->set('drink_water_notification', 1, 'EX', $_ENV['DRINK_WATER_INTERVAL']);
            $channels = explode(',', $_ENV['DRINK_WATER_CHANNELS']);

            foreach ($channels as $channel) {
                $drinkWaterIndex = array_rand($config['images']['drink_water']);
                $message = new Embed($discord);
                $message->setTitle('Hora de beber água!')
                        ->setImage($config['images']['drink_water'][$drinkWaterIndex]);
                $discord->getChannel($channel)->sendMessage(MessageBuilder::new()->addEmbed($message));
            }
        }
    });

    /*
        This fetches all the voice channels in the guild and adds their users to the voice channel cache 
        in order to give them coins for staying in the voice channel
    */
    $discord->guilds->fetch($_ENV['GUILD_ID'])->done(function (Guild $guild) use ($discord, &$redis) {
        foreach ($guild->channels as $channel) {
            if (!$channel->type == Channel::TYPE_GUILD_VOICE) return;

            $members_on_voice = $discord->getChannel($channel->id)->members->toArray();

            foreach ($members_on_voice as $member) {
                $discord->getLogger()->debug(
                    sprintf(
                        "Presence: username: %s - added to voice users joined list",
                        $member->member->user->username,
                        time()
                    )
                );

                $redis->set("presence_coins:" . $member->member->user->id, json_encode([
                    "entry_time" => time(),
                    "id" => $member->member->user->id
                ]));
            }
        }
    });

    $botStartedAt = date('Y-m-d H:i:s');

    echo " " . PHP_EOL;
    echo " " . PHP_EOL;
    echo "███████████              █████                               ███████████            █████    " . PHP_EOL;
    echo "░░███░░░░░███            ░░███                               ░░███░░░░░███          ░░███    " . PHP_EOL;
    echo " ░███    ░███ █████ ████ ███████    ██████   ██████   ██████  ░███    ░███  ██████  ███████  " . PHP_EOL;
    echo " ░██████████ ░░███ ░███ ░░░███░    ███░░███ ███░░███ ███░░███ ░██████████  ███░░███░░░███░   " . PHP_EOL;
    echo " ░███░░░░░███ ░███ ░███   ░███    ░███████ ░███ ░░░ ░███ ░███ ░███░░░░░███░███ ░███  ░███    " . PHP_EOL;
    echo " ░███    ░███ ░███ ░███   ░███ ███░███░░░  ░███  ███░███ ░███ ░███    ░███░███ ░███  ░███ ███" . PHP_EOL;
    echo " ███████████  ░░████████  ░░█████ ░░██████ ░░██████ ░░██████  ███████████ ░░██████   ░░█████ " . PHP_EOL;
    echo "░░░░░░░░░░░    ░░░░░░░░    ░░░░░   ░░░░░░   ░░░░░░   ░░░░░░  ░░░░░░░░░░░   ░░░░░░     ░░░░░  " . PHP_EOL;
    echo " " . PHP_EOL;
    echo " Font: DOS Rebel" . PHP_EOL;
    echo " Bot is ready! " . PHP_EOL;
    echo " Started at: $botStartedAt " . PHP_EOL;
    echo " " . PHP_EOL;
    echo " " . PHP_EOL;
});

$discord->on(DiscordEvent::GUILD_MEMBER_UPDATE, new GuildMemberUpdate($discord, $config, $redis, $userRepository, $userChangeHistoryRepository));
$discord->on(DiscordEvent::MESSAGE_CREATE, new MessageCreate($discord, $config, $redis, $talkRepository));
$discord->on(DiscordEvent::VOICE_STATE_UPDATE, new VoiceStateUpdate($discord, $redis, $userRepository));
$discord->listenCommand('test', new TestCommand($discord, $config, $redis));
$discord->listenCommand('codigo', new CodeCommand($discord, $config, $redis));
$discord->listenCommand('coins', new CoinsCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand('mestre', new AskCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand('perguntar', new AskingCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand('picasso', new PaintCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand('avioeszinhos', new FlyCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand('transferir', new TransferCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand(['top', 'patroes'], new TopCeosCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
$discord->listenCommand(['evento', 'anunciar'], new AdvertiseCommand($discord, $config, $eventRepository, $eventChoiceRepository));
$discord->listenCommand('apostar', new BetCommand($discord, $config, $userRepository, $eventRepository, $eventBetsRepository));
$discord->listenCommand(['evento', 'criar'], new CreateCommand($discord, $config, $eventRepository));
$discord->listenCommand(['evento', 'fechar'], new CloseCommand($discord, $config, $eventRepository, $eventChoiceRepository));
$discord->listenCommand(['evento', 'encerrar'], new FinishCommand($discord, $config, $eventRepository, $eventChoiceRepository));
$discord->listenCommand(['evento', 'listar'], new ListCommand($discord, $config, $eventRepository, $eventChoiceRepository));
$discord->listenCommand(['roleta', 'criar'], new RouletteCreateCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository));
$discord->listenCommand(['roleta', 'listar'], new RouletteListCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository));
$discord->listenCommand(['roleta', 'fechar'], new RouletteCloseCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository));
$discord->listenCommand(['roleta', 'girar'], new RouletteFinishCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository));
$discord->listenCommand(['roleta', 'apostar'], new RouletteExposeCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository));

$discord->run();
