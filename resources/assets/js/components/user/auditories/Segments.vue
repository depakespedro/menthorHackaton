<template>
    <div class="panel-default">
        <!--<div class="panel-heading">-->
            <!--Сегменты-->
        <!--</div>-->
        <div class="panel-body">
            <div>
                <div class="replies-wrapper">
                    <div class="media" v-for="segment in segments">
                        <div class="media-body user-reply">
                            <div class="message-text" @click="selectSegment(segment.id)">
                                <span :class="{'selected-segment' : selectedSegmentId == segment.id}">
                                    {{ segment.title }}
                                    <button @click="deleteSegment(segment)">X</button>
                                </span>
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

        props: ['segments'],

        data() {
            return {
                selectedSegmentId: null
            }
        },

        methods: {
            selectSegment(segmentId) {
                this.selectedSegmentId = segmentId;
                this.$emit('selectedSegment', segmentId);
            },

            deleteSegment(segment) {
                api.deleteSegment(segment.id).then((response) => {
                    this.$emit('deletedSegment');
                });
            }
        },

    }
</script>

<style scoped>
    .panel-default{
        border-bottom: 1px solid white;
        margin-bottom: 60px;
    }
    .message-text span{
        display: inline-block;
        padding: 2px 10px;
        font-size: 14px;
        font-family: "RalewayLight", "Helvetica Neue", Helvetica, Arial, sans-serif;
        background-color: #fff;
        margin-right: 10px;
        /*margin-top: 10px;*/
        border-radius: 6px;
        cursor: pointer;
        transition: all .3s;
    }
    .message-text span button{
        margin-left: 20px;
        z-index: 1;
        padding: 0;
        border: none;
        background: none;
        cursor: pointer;
        font-family: Arial;
    }
    .media{
        display: inline-block;
        margin: 0;
    }
    .media-body {
        width: unset;
    }
    .message-text span.selected-segment{
        background-color: #00b7f4;
    }
</style>
