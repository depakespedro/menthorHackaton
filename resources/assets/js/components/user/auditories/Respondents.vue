<template>
    <div class="panel-default">
        <!--<div class="panel-heading">-->
            <!--Респонденты-->
        <!--</div>-->
        <div class="panel-body">
            <div>
                <div class="replies-wrapper">
                    <div class="media" v-for="respondent in respondents">
                        <div class="media-body user-reply">
                            <div class="message-text" @click="selectRespondent(respondent.id)">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import api from '../../../store/api/all'

    export default {
        name: "respondents",

        props: ['projectId', 'segmentId'],

        data () {
            return {
                respondents: null,
                selectedRespondentId: null
            }
        },

        methods: {
            getRespondents(projectId, segmentId) {
                api.getRespondentsSegment(projectId, segmentId).then((response) => {
                    this.respondents = response.data.respondents.data;
                    console.log(this.respondents);
                })
            },

            selectRespondent(respondentId) {
                this.selectedRespondentId = respondentId;
                this.$emit('selectedRespondent', respondentId);
            }
        },

        watch: {
            segmentId (loading) {
                this.getRespondents(this.projectId, this.segmentId)
            },

            projectId (loading) {
                this.getRespondents(this.projectId, this.segmentId)
            }
        },

        created() {

        }
    }
</script>

<style scoped>
    .replies-wrapper{
        margin: 0 -15px;
    }
    .media{
        margin: 0;
    }
    .user-reply{
        padding: 12px 15px;
        margin: 0 -15px;
        border-bottom: 3px solid #f0f0f1;
        font-size: 14px;
        font-family: "RalewayLight", "Helvetica Neue", Helvetica, Arial, sans-serif;
        position: relative;
        cursor: pointer;
    }
    .message-text p{
        margin: 0;
    }
</style>
