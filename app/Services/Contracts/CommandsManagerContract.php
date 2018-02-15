<?php 

namespace App\Services\Contracts;

interface CommandsManagerContract
{
    /**
     * Запускает обрабоку комманды бота
     * 
     * @return OutputMessage or null
     */
    public function run($name, array $arguments = []);

    /**
     * Старт бота
     * 
     * @return OutputMessage or null
     */
    public function start($arguments);

    /**
     * Старт опроса
     * 
     * @return OutputMessage or null
     */
    public function start_survey($arguments, $survey = null);

    /**
     * Создание опроса
     * 
     * @return OutputMessage or null
     */
    public function create_survey();

    /**
     * Выбор опроса
     * 
     * @return OutputMessage or null
     */
    public function select_survey();

    /**
     * Регистрация пользователя
     * 
     * @return OutputMessage or null
     */
    public function register();

    /**
     * Создание опроса
     * 
     * @return OutputMessage or null
     */
    public function next_question($arguments);

    /**
     * Вывод бонусов (призов)
     * 
     * @return OutputMessage or null
     */
    public function bonuses($arguments);

    /**
     * Помощь
     * 
     * @return OutputMessage or null
     */
    public function help();

    public function testing();
}
