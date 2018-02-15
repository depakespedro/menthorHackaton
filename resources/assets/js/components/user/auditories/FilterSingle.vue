<template>
    <div>
        <form @change="changedFormFilter()">

            <div class="input-group">

                <select v-model="currentFilter">
                    <option :value="null">Не выбрано</option>
                    <option v-for="filter in filters" :value="filter">
                        {{ filter.title }}
                    </option>
                </select>




                <span v-if="conditions">

                    <span v-if="['choiceQuestion', 'multipleQuestion'].includes(currentScope)">
                        <select v-model="currentSurvey">
                            <option :value="null">Не выбрано</option>
                            <option v-for="survey in currentSurveys" :value="survey">
                                {{ survey.title }}
                            </option>
                        </select>
                        <select v-model="currentQuestion">
                            <option :value="null">Не выбрано</option>
                            <option v-for="question in currentSurvey.questions" :value="question">
                                {{ question.title }}
                            </option>
                        </select>
                        <select v-model="currentCondition" >
                            <option :value="null">Не выбрано</option>
                            <option v-for="condition in conditions" :value="condition">
                                {{ condition.title }}
                            </option>
                        </select>
                        <select v-model="currentAnswers" multiple>
                            <option :value="null">Не выбрано</option>
                            <option v-for="answer in currentQuestion.answers" :value="answer">
                                {{ answer.answer_text }}
                            </option>
                        </select>
                    </span>

                    <span v-else-if="['freeTextQuestion', 'guessQuestion'].includes(currentScope)">
                        <select v-model="currentSurvey">
                            <option :value="null">Не выбрано</option>
                            <option v-for="survey in currentSurveys" :value="survey">
                                {{ survey.title }}
                            </option>
                        </select>
                        <select v-model="currentQuestion">
                            <option :value="null">Не выбрано</option>
                            <option v-for="question in currentSurvey.questions" :value="question">
                                {{ question.title }}
                            </option>
                        </select>
                        <select v-model="currentCondition" >
                            <option :value="null">Не выбрано</option>
                            <option v-for="condition in conditions" :value="condition">
                                {{ condition.title }}
                            </option>
                        </select>
                        <input v-model="currentAnswers">
                    </span>

                    <span v-else>
                        <select v-model="currentCondition" >
                            <option :value="null">Не выбрано</option>
                            <option v-for="condition in conditions" :value="condition">
                                {{ condition.title }}
                            </option>
                        </select>
                        <input v-model="currentArgument">
                    </span>

                </span>
            </div>
        </form>
    </div>


</template>

<script>

    import api from './../../../store/api/all'

    export default {

        props: ['filters', 'filter', 'id', 'projectId'],

        data() {
            return {
                currentFilter: null,
                currentCondition: null,
                currentArgument: null,
                currentScope: null,
                conditions: null,

                currentSurveys: [],
                currentSurvey: [],
                currentQuestion: [],
                currentAnswers: [],
            }
        },

        methods: {
            changedFormFilter() {
                if (this.currentFilter.conditions.data.length > 0) {
                    this.conditions = this.currentFilter.conditions.data;
                    this.currentScope = this.currentFilter.scope;

                    //если в селекте выбрали тип вопроса галочка, то подгружаем все данные по опросам данного проекта
                    if (['choiceQuestion', 'multipleQuestion', 'freeTextQuestion', 'guessQuestion'].includes(this.currentScope)) {
                        this.loadSurveys();
                    }

                    if (Array.isArray(this.currentAnswers)) {
                        console.log('answer');
                        this.currentArgument = JSON.stringify({
                            answers: this.currentAnswers,
                            question: this.currentQuestion,
                            survey: this.currentSurvey,
                        });
                    } else {
                        console.log(this.currentAnswers);
                        this.currentArgument = JSON.stringify({
                            answers: this.currentAnswers,
                            question: this.currentQuestion,
                            survey: this.currentSurvey,
                        });
                    }

                    if (this.currentFilter && this.currentCondition && this.currentArgument) {
                        this.changedFilter();
                    }
                } else {
                    this.conditions = null;
                    this.currentArgument = null;
                    this.currentCondition = null;
                    this.currentScope = null;
                    this.surveys= [];
                    this.changedFilter();
                }
            },

            changedFilter() {
                this.$emit('changedFilter', {
                    id: this.id,
                    filter: this.currentFilter,
                    condition: this.currentCondition,
                    argument: this.currentArgument,
                });
            },

            loadSurveys() {
                api.getFullSurveys(this.projectId, this.currentScope).then((response) => {
                    this.currentSurveys = $.parseJSON(response.data.surveys);
                });
            }
        },

        watch: {
            filter() {
                console.log(this.filter.scope);
                this.currentFilter = this.filter.filter;
                this.currentCondition = this.filter.condition;
                this.currentArgument = this.filter.argument;
                this.currentScope = this.filter.scope;
                this.conditions = null;
            },

            currentSurvey() {
                this.currentQuestion = [];
                this.currentAnswers = [];
            },
        },
    }
</script>

<style scoped>
    .input-group select, .input-group input{
        display: block;
        padding: 10px;
        border: none;
        background: white;
        margin-bottom: 5px;
        border-radius: 10px;
    }
</style>