<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use Monolog\Logger as Monolog;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Discord\Discord;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\User\Member;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event as DiscordEvent;
use Chorume\Database\Db;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\EventBet;
use Chorume\Repository\Talk;
use Chorume\Repository\UserCoinHistory;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use Chorume\Repository\UserChangeHistory;
use Chorume\Application\Commands\Code\CodeCommand;
use Chorume\Application\Commands\Event\AdvertiseCommand;
use Chorume\Application\Commands\Event\BetCommand;
use Chorume\Application\Commands\Event\CloseCommand;
use Chorume\Application\Commands\Event\CreateCommand;
use Chorume\Application\Commands\Event\FinishCommand;
use Chorume\Application\Commands\Event\ListCommand;
use Chorume\Application\Commands\Generic\CoinsCommand;
use Chorume\Application\Commands\Generic\TopForbesCommand;
use Chorume\Application\Commands\Generic\TransferCommand;
use Chorume\Application\Commands\LittleAirplanes\FlyCommand;
use Chorume\Application\Commands\Master\AskCommand;
use Chorume\Application\Commands\Asking\AskingCommand;
use Chorume\Application\Commands\Picasso\PaintCommand;
use Chorume\Application\Commands\Roulette\CloseCommand as RouletteCloseCommand;
use Chorume\Application\Commands\Roulette\CreateCommand as RouletteCreateCommand;
use Chorume\Application\Commands\Roulette\ExposeCommand as RouletteExposeCommand;
use Chorume\Application\Commands\Roulette\FinishCommand as RouletteFinishCommand;
use Chorume\Application\Commands\Roulette\ListCommand as RouletteListCommand;
use Chorume\Application\Commands\Test\TestCommand;
use Chorume\Application\Events\MessageCreate;
use Chorume\Application\Events\GuildMemberUpdate;
use Chorume\Application\Events\VoiceStateUpdate;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Guild\Guild;
use Discord\Parts\WebSockets\VoiceStateUpdate as WebSocketsVoiceStateUpdate;

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

$logger = new Monolog('ChorumeCoins');

if (getenv('ENVIRONMENT') === 'production') {
    $rotatingHandler = new RotatingFileHandler(
        __DIR__ . '/../logs/chorumebot.log',
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

$discord->on('init', function (Discord $discord) use ($userRepository, $redis) {
    // Initialize application commands
    $initializeCommandsFiles = glob(__DIR__ . '/Application/Initialize/*Command.php');

    foreach ($initializeCommandsFiles as $initializeCommandsFile) {
        $initializeCommand = include $initializeCommandsFile;

        $command = new Command($discord, $initializeCommand);
        $discord->application->commands->save($command);
    }

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

                $redis->set("voice_cache:" . $member->member->user->id, json_encode([
                    "entry_time" => time(),
                    "id" => $member->member->user->id
                ]));
            }
        }
    });

    $botStartedAt = date('Y-m-d H:i:s');

    echo "  _______                           ___      __   " . PHP_EOL;
    echo " / ___/ / ___  ______ ____ _ ___   / _ )___ / /_  " . PHP_EOL;
    echo "/ /__/ _ / _ \/ __/ // /  ' / -_) / _  / _ / __/  " . PHP_EOL;
    echo "\___/_//_\___/_/  \_,_/_/_/_\__/ /____/\___\__/   " . PHP_EOL;
    echo "                                                  " . PHP_EOL;
    echo "                 Bot is ready!                    " . PHP_EOL;
    echo "         Started at: $botStartedAt                " . PHP_EOL;
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
$discord->listenCommand(['top', 'forbes'], new TopForbesCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository));
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
