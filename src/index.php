<?php

require __DIR__ . '/../vendor/autoload.php';
require 'Helpers/FindHelper.php';

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
use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\EventBet;
use Chorume\Repository\Talk;
use Chorume\Repository\UserCoinHistory;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
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

    // DRINK_WATER_REMEMBER_ENABLE=1
    // DRINK_WATER_INTERVAL=3600

    // update users_coins_history set type = 'RouletteBet' where type = 'BetRoulette'
    // update users_coins_history set type = 'EventBet' where type = 'Event'

    // $guild = $discord->guilds->first();
    // echo "asdf";
    // $guild->members->fetch($_ENV['BOT_ID'])->then(function (Member $member) {
    //     var_dump($member->channels);
    // })->done();

    // Little Airplanes Sound
    // $channel = $discord->getChannel($interaction->channel_id);
    // $audio = __DIR__ . '/../../../Audio/agua.mp3';
    // $voice = $discord->getVoiceClient($channel->guild_id);

    // if ($channel->isVoiceBased()) {
    //     if ($voice) {
    //         $discord->getLogger()->debug('Voice client already exists, playing Little Airplanes audio...');

    //         $voice->playFile($audio);
    //     } else {
    //         $discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio) {
    //             $discord->getLogger()->debug('Playing Little Airplanes audio...');

    //             $voice->playFile($audio);
    //         });
    //     }
    // }

    // Presence in beta testing
    // Members are not getting updated when they leave the voice channel
    // echo "Presence in beta testing" . PHP_EOL;

    // if ($_ENV['PRESENCE_EXTRA_COINS_ENABLE'] == 1) {
    //     echo "Presence enabled" . PHP_EOL;
    //     $presenceChannels = explode(',', getenv('PRESENCE_EXTRA_COINS_CHANNELS'));
    //     $loop = $discord->getLoop();
    //     $loop->addPeriodicTimer($_ENV['PRESENCE_EXTRA_COINS_CHECK_TIME'], function () use ($discord, $presenceChannels, $redis, $userRepository) {
    //         echo "Presence check!" . PHP_EOL;

    //         foreach ($presenceChannels as $channelId) {
    //             $channel = $discord->getChannel($channelId);
    //             echo "=================================" . PHP_EOL;

    //             if (!$channel->isVoiceBased()) {
    //                 continue;
    //             }

    //             $presenceList = json_decode($redis->get('presence:' . $channelId) ?? '[]');
    //             $presenceNewList = [];
    //             $membersList = $channel->members->toArray();

    //             foreach ($membersList as $member) {
    //                 echo $member['user']->global_name . PHP_EOL;

    //                 if ($member['user']->bot) {
    //                     continue;
    //                 }

    //                 var_dump(array_column($presenceList, 'username'));

    //                 $found = array_search($member['user']->id, array_column($presenceList, 'id'));

    //                 if ($found === false) {
    //                     if (!$member->self_deaf) {
    //                         continue;
    //                     }

    //                     $discord->getLogger()->debug('User new: ' . $member['user']->global_name);
    //                     $presenceNewList[] = [
    //                         'id' => $member['user']->id,
    //                         'username' => $member['user']->username,
    //                         'global_name' => $member['user']->global_name,
    //                         'presence' => time(),
    //                         'accumulated' => 0,
    //                     ];
    //                 } else {
    //                     $discord->getLogger()->debug('User exists: ' . $member['user']->global_name);
    //                     $currentPresence = $presenceList[$found]->presence;
    //                     $presenceDiff = time() - $currentPresence;

    //                     if ($presenceDiff >= $_ENV['PRESENCE_EXTRA_COINS_WIN_TIME']) {
    //                         $presenceList[$found]->presence = time();

    //                         // $discord->getLogger()->debug('User ' . $member['user']->global_name . ' presence diff: ' . $presenceDiff);

    //                         if (!$member->self_deaf) {
    //                             // $discord->getLogger()->debug('User received extra coins: ' . $member['user']->global_name);
    //                             $presenceList[$found]->accumulated += $_ENV['PRESENCE_EXTRA_COINS_AMOUNT'];
    //                             $presenceNewList[] = $presenceList[$found];
    //                             $userRepository->giveCoins($member['user']->id, $_ENV['PRESENCE_EXTRA_COINS_AMOUNT'], 'Presence', json_encode($presenceList[$found]));
    //                         }
    //                     } else {
    //                         $presenceNewList[] = $presenceList[$found];
    //                     }
    //                 }
    //             }

    //             $redis->set('presence:' . $channelId, json_encode($presenceNewList));
    //         }
    //     });
    // }

    $botStartedAt = date('Y-m-d H:i:s');

    echo "  _______                           ___      __   " . PHP_EOL;
    echo " / ___/ / ___  ______ ____ _ ___   / _ )___ / /_  " . PHP_EOL;
    echo "/ /__/ _ / _ \/ __/ // /  ' / -_) / _  / _ / __/  " . PHP_EOL;
    echo "\___/_//_\___/_/  \_,_/_/_/_\__/ /____/\___\__/   " . PHP_EOL;
    echo "                                                  " . PHP_EOL;
    echo "                 Bot is ready!                    " . PHP_EOL;
    echo "         Started at: $botStartedAt                " . PHP_EOL;
});

$discord->on(DiscordEvent::MESSAGE_CREATE, new MessageCreate($discord, $config, $redis, $talkRepository));
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
