<template>
    <div id="auditories" class="container-fluid">
        <select-project v-if="projects.length" :projects="projects" :selectedId="currentProjectId" entity="auditories"></select-project>

        <div class="row">
            <div class="segments-column col-md-4 col-xs-12">
                <segments :projectId="currentProjectId" :segments="segments" @selectedSegment="changeCurrentSegment($event)" @deletedSegment="updateSegments()"></segments>
                <constructor-segment :projectId="currentProjectId" @createdSegment="updateSegments()"></constructor-segment>
            </div>

            <div class="respondents-column col-md-4 col-xs-12 br-2">
                <respondents :projectId="currentProjectId" :segmentId="currentSegmentId" @selectedRespondent="changeCurrentRespondent($event)"></respondents>

            </div>
            <div class="respondent-column col-md-4 col-xs-12">
                <respondent :respondentId="currentRespondentId"></respondent>
            </div>
            <div class="respondent-column col-md-4 col-xs-12">
                <intersections-segments :segments="segments"></intersections-segments>
            </div>
        </div>
    </div>
</template>
<script>

    import api from '../../../store/api/all'

    Vue.component('respondents', require('./Respondents'));
    Vue.component('respondent', require('./Respondent'));
    Vue.component('segments', require('./Segments'));
    Vue.component('constructor-segment', require('./ConstructorSegment'));
    Vue.component('intersections-segments', require('./IntersectionsSegments'));

    export default {
        data() {
            return {
                projects: [],
                currentProjectId: null,
                currentRespondentId: null,
                currentSegmentId: null,
                segments: null,
            }
        },

        methods: {
            changeCurrentRespondent(respondentId) {
                this.currentRespondentId = respondentId;
            },

            changeCurrentSegment(segmentId) {
                this.currentSegmentId = segmentId;
            },

            updateSegments() {
                this.getSegments(this.currentProjectId);
            },

            getSegments(projectId) {
                api.getSegments(projectId).then((response) => {
                    this.segments = response.data.segments.data;
                })
            },
        },

        mounted() {

            //подгрузка проекта
            let projectId = this.$route.params.project || null;
            this.currentProjectId = projectId;
            this.projects = this.$root.userProjects;

            //подгрузка сегментов
            this.getSegments(this.currentProjectId);

        }
    }
</script>

<style scoped>
    .respondents-column{
        min-height: 100vh;
    }
    .br-2{
        border-right: 2px solid #efefef;
    }
    .title__kostil:before{
        content: 'Аудитория';
    }
</style>
