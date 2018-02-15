<template>
    <div class="panel-default">
        <!--<div class="panel-heading">-->
            <!--Респондент-->
        <!--</div>-->
        <div class="panel-body" v-if="respondent">
            <div>

                <div class="respondent-wrapper">
                    <div class="respondent-body">
                        <div class="respondent-avatar">
                            <div class="circle-avatar"><img v-bind:src="respondent.avatar"
                                                            v-bind:title="respondent.name"
                                                            v-bind:alt="respondent.name + ' avatar'"></div>
                        </div>
                        <!--<div class="respondent-name">{{ respondent.id }}</div>-->
                        <div class="respondent-name">
                            <p v-if="respondent.name">
                                {{ respondent.name }}
                            </p>
                            <p v-else-if="respondent.email">
                                {{ respondent.email }}
                            </p>
                            <p v-else="respondent.id">
                                {{ respondent.id }}
                            </p>
                        </div>

                    </div>
                    <div class="respondent-information">
                        <div class="respondent-information-mail" v-if="respondent.email">{{ respondent.email }}</div>
                        <div class="respondent-information-phone" v-if="respondent.phone">{{ respondent.phone }}</div>
                        <div class="respondent-information-year" v-if="respondent.wage">{{ respondent.wage }}</div>
                        <div class="respondent-information-location" v-if="respondent.location">{{ respondent.location
                            }}
                        </div>
                        <div class="respondent-information-sex" v-if="respondent.sex">{{ respondent.sex }}</div>
                        <div class="respondent-information-family" v-if="respondent.family">{{ respondent.family }}
                        </div>
                        <div class="respondent-information-childrens" v-if="respondent.childrens">{{
                            respondent.childrens }}
                        </div>
                        <div class="respondent-information-education" v-if="respondent.education">{{
                            respondent.education }}
                        </div>
                        <div class="respondent-information-tg" v-if="respondent.isActiveTelegram">{{
                            respondent.isActiveTelegram }}
                        </div>
                        <div class="respondent-information-fb" v-if="respondent.isActiveFacebook">{{
                            respondent.isActiveFacebook }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

    import api from '../../../store/api/all'

    export default {
        props: [
            'respondentId'
        ],

        data() {
            return {
                respondent: null
            }
        },

        methods: {
            getRespondent(id) {
                api.getRespondent(id).then((response) => {
                    this.respondent = response.data.respondent.data;
                });
            }
        },

        watch: {
            respondentId (loading) {
                this.getRespondent(this.respondentId)
            }
        },

        created() {
            this.getRespondent(this.respondentId)
        },
    }
</script>

<style>
    .respondent-information{
        font-size: 14px !important;
        font-family: "RalewayLight", "Helvetica Neue", Helvetica, Arial, sans-serif !important;
    }
</style>
