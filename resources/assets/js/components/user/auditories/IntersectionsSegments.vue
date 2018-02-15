<template>
    <div class="panel-default">
        <div class="panel-body">
            <div>
                <div class="replies-wrapper">
                    <div class="filter-segment">
                        <ul class="intersection__list">
                            <li v-for="intersetionSegment in intersectionsSegments">
                                <span class="intersection__name">Название пересечения : {{ intersetionSegment.title}}</span>
                                <span class="intersection__segment">{{ intersetionSegment.segment_one.title }}</span>
                                <i><-></i>
                                <span class="intersection__segment">{{ intersetionSegment.segment_two.title }}</span>
                                <button class="btn-close" @click="deleteIntersectionSegment(intersetionSegment.id)">X</button>
                            </li>
                        </ul>
                    </div>

                    <input class="intersection__input" type="text" placeholder="Нвазвание связи" v-model="intersectionSegment.intersectionSegmentTitle">
                    <select class="intersection__select" style="margin-right: 44px;" v-model="intersectionSegment.segmentOne">
                        <option v-for="segment in segments" :value="segment.id">{{ segment.title }}</option>
                    </select>
                    <select class="intersection__select" v-model="intersectionSegment.segmentTwo">
                        <option v-for="segment in segments" :value="segment.id">{{ segment.title }}</option>
                    </select>
                    <button class="btn-add-segment-intersection" @click="createIntersectionSegment()">Создать связь</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import api from './../../../store/api/all'

    export default {

        props: ['segments'],

        data() {
            return {
                segmentOneId: null,
                segmentTwoId: null,
                projectId: null,
                intersectionsSegments: [],
                intersectionSegment: {
                    projectId: null,
                    segmentOne: null,
                    segmentTwo: null,
                    intersectionSegmentTitle: ''
                },
            }
        },

        methods: {
            getIntersectionsSegments() {
                api.getIntersectionsSegments(this.projectId).then((response) => {
                    this.intersectionsSegments = response.data.intersectionsSegments;
                });
            },

            createIntersectionSegment() {

                this.intersectionSegment.projectId = this.projectId;

                api.createIntersectionSegment(
                    this.intersectionSegment
                ).then((response) => {
                    this.getIntersectionsSegments();
                });
            },

            deleteIntersectionSegment(intersectionSegmentId) {
                api.deleteIntersectionSegment(intersectionSegmentId).then((response) => {
                    this.getIntersectionsSegments();
                });
            }
        },


        mounted() {
            //подгрузка проекта
            this.projectId = this.$route.params.project || null;

            this.getIntersectionsSegments();
        }
    }
</script>

<style scoped>
    .replies-wrapper{
        font-family: RalewayLight, "Helvetica Neue", Helvetica, Arial, sans-serif

    }
    .intersection__list{
        margin: 0;
        padding: 0;
        list-style: none;
        margin-bottom: 60px;
    }
    .intersection__list li{
        margin-bottom: 10px;
    }
    .intersection__select{
        padding: 10px;
        border: none;
        margin-bottom: 15px;
        border-radius: 10px;
        background-color: #efefef;
        display: inline-block;
        width: 45%;
    }
    .btn-close{
        cursor: pointer;
        transition: all .3s;
        border: none;
        font-weight: bold;
        font-size: 20px;
        background-color: transparent;
        vertical-align: sub;
        margin-left: 20px;
    }
    .intersection__input{
        border: none;
        border-radius: 7px;
        padding: 8px 10px;
        margin-bottom: 15px;
        width: 100%;
        background-color: #efefef;
        outline: none;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
    }
    .btn-add-segment-intersection{
        background-color: #00b7f4;
        border: 1px solid transparent;
        padding: 10px 5px;
        border-radius: 6px;
        outline: none;
        cursor: pointer;
        box-shadow: 0 6px 6px #dededf;
        transition: all .3s;
        color: white;
        display: block;
        margin: 0 auto;
    }
    .btn-add-segment-intersection:hover{
        border-color: #00b7f4;
        color: #00b7f4;
        background-color: #fff;
        box-shadow: 0 10px 6px #dededf;
    }
</style>
