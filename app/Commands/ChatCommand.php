<?php

namespace App\Commands;

use App\Events\DialogsHandler;
use App\Events\HistoryHandler;
use App\Events\InputHandler;
use App\Events\UpdateHandler;
use App\Events\UserHandler;
use danog\MadelineProto\API;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Clue\React\Stdio\Stdio;
use React\EventLoop\Factory;

class ChatCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'chat';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start the chat';

    protected $MadelineProto = null;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $loop = Factory::create();
        $stdio = new Stdio($loop);

        echo "\nBooting up Telegram. Hold on...";

        ob_start(function ($chunk) use ($stdio) {
            $stdio->write($chunk);
            return '';
        }, 1);

        $this->MadelineProto = new API(config('telegram.sessions.path'), config('telegram', []));
        $this->MadelineProto->start();

        echo "\nSuccessfully booted Telegram.\n\n";

        // get current logged in user
        $userHandler = UserHandler::getInstance($this->MadelineProto);

        // show selection for chat
        $dialogHandler = new DialogsHandler($this->MadelineProto);
        $dialogHandler->getDialogsMenuOptions();
        $currentChat = $this->menu('Choose a chat', $dialogHandler->getDialogsMenuOptions())->open();
        if (empty($currentChat)) {
            $this->info("Shutting down chat... :(");
            die();
        }


        // show logged in user and selected chat
        $userHandler->showUserName();
        $this->info("You selected the chat with the id: #$currentChat");
        echo PHP_EOL;


        // show input prompt
        $stdio->getReadline()->setPrompt(sprintf("%-{$userHandler->getChatDetailLength()}s", "You") . " > ");


        // show chat history
        $historyHandler = new HistoryHandler($this->MadelineProto, $currentChat);
        $historyHandler->showHistory();


        // check for new messages
        $updateHandler = new UpdateHandler($this->MadelineProto, $currentChat);
        $inputHandler = new InputHandler($this->MadelineProto, $currentChat);

        $timer = $loop->addPeriodicTimer(1, [$updateHandler, 'handleUpdates']);
        $stdio->on('data', [$inputHandler, 'handleInput']);

        if (!$stdio->isReadable()) {
            $loop->cancelTimer($timer);
            $stdio->end();
        }
        $loop->run();
    }

    /**
     * Define the command's schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
