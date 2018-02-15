<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\Contracts\SurveybotContract;
use App\Services\Repositories\TelegramBotRepository;
use App\Services\Repositories\FacebookBotRepository;
use App\Services\Repositories\WebBotRepository;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Bot\Telegram\WebhookController as TelegramWebhook;
use App\Http\Controllers\Bot\Facebook\WebhookController as FacebookWebhook;
use App\Http\Controllers\Bot\Web\WebhookController as WebbotWebhook;

use App\Services\Bots\Facebook\Commands\Command as FacebookCommand;
use App\Services\Bots\Web\Commands\Command as WebbotCommand;

class SurveybotServiceProvider extends ServiceProvider
{
    const TELEGRAM = 'telegram';
    const FACEBOOK = 'facebook';
    const WEB = 'web';

    const TELEGRAM_ACTION = 'App\Http\Controllers\WebhookController@telegram';
    const FACEBOOK_ACTION = 'App\Http\Controllers\WebhookController@facebook';
    const WEB_ACTION = 'App\Http\Controllers\WebhookController@web';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepository();
    }

    public function registerRepository()
    {
        $this->app->singleton(SurveybotContract::class, function ($app, $params){

            //инициализация по типу который нам требуется
            if(isset($params['botType']) and !empty($params['botType'])){
                $type = $params['botType'];
                if($type === self::TELEGRAM)
                    return new TelegramBotRepository($params);
                elseif ($type === self::FACEBOOK)
                    return new FacebookBotRepository($params);
                elseif ($type === self::WEB)
                    return new WebBotRepository($params);
            }

            //автоматическая инициализация в зависимости  на какой хук постучались
            $fullRoute = Route::currentRouteAction();
            if(!is_null($fullRoute) and !empty($fullRoute)){
                if($fullRoute === self::TELEGRAM_ACTION)
                    return new TelegramBotRepository($params);
                elseif($fullRoute === self::FACEBOOK_ACTION)
                    return new FacebookBotRepository($params);
                elseif($fullRoute === self::WEB_ACTION)
                    return new WebBotRepository($params);
            }

            return null;
        });
    }
}
