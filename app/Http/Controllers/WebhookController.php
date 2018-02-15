<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Services\BotObjects\Messages\Output\TextMessage;
use App\Services\Contracts\CommandsManagerContract;

class WebhookController extends Controller
{
    protected $bot;

    public function __construct(Request $request)
    {
        $this->middleware('auth.bot.web')->only('web');
        $this->middleware('auth.bot.facebook')->only('facebook');
        $this->middleware('auth.bot.telegram')->only('telegram');
    }

    public function telegram(Request $request)
    {
        Log::info('telegram $request = ' . print_r($request->toArray(), true));

        $this->bot = bot();
        $message = $this->bot->getMessage();

        Log::info('telegram messageInput = ' . print_r($message, true));

        $messageOutput = $message ? $this->transform($message) : null;

        if ($messageOutput){

            if(!is_array($messageOutput)){
                $this->bot->sendMessage($messageOutput, $message->getChatId());
            }

            foreach ($messageOutput as $message){
                $this->bot->sendMessage($message, $message->getChatId());
            }

        }

        return response()->make('ok', 200);
    }

    public function web(Request $request)
    {
        $data = $request->all();

        $allEvents = isset($data['events']) && is_array($data['events']) ? $data['events'] : [];

        $this->bot = bot();

        foreach ($allEvents as $event) {

            if (!is_array($event)) {
                continue;
            }

            $this->bot->setEvent($event);
            $message = $this->bot->getMessage();

            if (!$message) {
                continue;
            }

            // запрет на повторный прием обновления во время вопросов с задержкой (delay)
            $chatId = $message->getChatId();
            if (Cache::has('chat' . $chatId . 'delayed')) {
                continue;
            }

            Log::info('Webbot webhook messageInput = ' . print_r($message, true));

            $messageOutput = $message ? $this->transform($message) : null;

            if ($messageOutput) {

                Log::info('Webbot webhook messageOutput = ' . print_r($message, true));

                if (!is_array($messageOutput)) {
                    $this->bot->sendMessage($messageOutput, $message->getChatId());
                } else {
                    foreach ($messageOutput as $message) {
                        $this->bot->sendMessage($message, $message->getChatId());
                    }
                }
            }
        } // end foreach

        return response()->make('ok', 200);
    }

    public function facebook(Request $request)
    {
        try{
            $this->bot = bot();
            $message = $this->bot->getMessage();

            Log::info('New facebook messageInput = ' . print_r($message, true)
                . PHP_EOL
                . ' request = '.print_r($request->toArray(), true));

            $messageOutput = $message ? $this->transform($message) : null;

            Log::info('New facebook messageOtput = ' . print_r($messageOutput, true));

            if ($messageOutput){

                if(!is_array($messageOutput)){
                    $this->bot->sendMessage($messageOutput, $message->getChatId());
                }

                foreach ($messageOutput as $message){
                    $this->bot->sendMessage($message, $message->getChatId());
                }

            }

        }catch (\Exception $exception) {
            Log::error($exception->getMessage().' '.$exception->getFile().' '.$exception->getLine());
        }
        
        return response()->make('ok', 200);
    }

    protected function transform($messageInput)
    {
        $typeMessage = $messageInput->getMessageType();
        $commandsManager = app(CommandsManagerContract::class, [$this->bot, $messageInput]);

        if ($typeMessage === 'callback') {

            $commandName = $messageInput->getCommandName();
            $args = $messageInput->getArguments();

            $messageOutput = $commandsManager->run($commandName, $args);
        } elseif ($typeMessage === 'text') {

            if ($messageInput->surveyIsRunning()) {
                $cacheData = $messageInput->getSurveyCacheData();
                $command = 'next_question';
                $args = [
                    'survey_id' => $cacheData['survey_id'],
                    'question_id' => $cacheData['question_id'],
                    'answer_id' => null,
                    'custom_answer' => $messageInput->getText(),
                    'step' => $cacheData['step'],
                ];
            } else {

                //если опрос не пришел
                $command = 'help';
                $args = [];
            }

            $messageOutput = $commandsManager->run($command, $args);
        } else {

            $messageOutput = $commandsManager->run('help');

        }

        return $messageOutput;
    }
}
