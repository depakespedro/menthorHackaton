<?php 

namespace App\Services\Contracts;

interface QuestionsManagerContract
{
    /**
     * В зависимости от $currentAnswer (ответа респондента), возвращает следующий вопрос,
     * который надо отправить респонденту
     * 
     * @return App\Models\Question
     */
    public function getNextQuestion();

    /**
     * Возвращает предыдущий вопрос
     * 
     * @return App\Models\Question
     */
    public function getPrevQuestion();

    /**
     * $currentAnswer ответ респондента на предыдущий вопрос
     * 
     * @return mixed App\Models\Answer | string
     */
    public function getCurrentAnswer();

    /**
     * Возвращает текущий опрос
     * 
     * @return App\Models\Survey
     */
    public function getSurvey();
}
