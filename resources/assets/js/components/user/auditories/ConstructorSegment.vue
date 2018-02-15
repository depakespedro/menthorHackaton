<template>
    <div class="panel-default">
        <!--<div class="panel-heading">-->
            <!--Конструктор сегмента-->
        <!--</div>-->
        <div class="panel-body">
            <div>
                <div class="replies-wrapper">

                    <div class="filter-segment" v-for="(filter, index) in segment.filters">
                        <filter-single
                                :projectId="projectId"
                                :filters="filters"
                                :id="index"
                                :filter="filter"
                                @changedFilter="changedFilter($event)"
                        ></filter-single>
                        <button class="btn-close" @click="deleteFilter(index)">X</button>
                    </div>


                    <button class="btn-add-filter" @click="addFilter()">Добавить фильтр</button>

                    <br>
                    <input class="segment-input" v-model="segment.title" placeholder="имя сегмента">
                    <br>
                    <button class="btn-add-segment" @click="createSegment()">Сохранить сегмент</button>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import api from '../../../store/api/all'

    Vue.component('filter-single', require('./FilterSingle'));

    export default {

        props: ['projectId'],

        data() {
            return {
                segment: {
                    filters: [],
                    title: ''
                },
                filters: null,
            }
        },

        methods: {
            getFilters() {
                api.getFilters().then((response) => {
                    this.filters = response.data.filters.data;
                    console.log(this.filters);
                });
            },

            changedFilter(event) {
                this.segment.filters[event.id] = event;
            },

            addFilter() {
                this.segment.filters.push({
                    filter: null,
                    condition: null,
                    argument: null,
                });
            },

            createSegment() {
                if (this.segment.title == '' || this.segment.title == ' ') {
                    return;
                }

                api.createSegment(this.projectId, this.segment).then((response) => {
                    this.segment = {
                        filters: [],
                        title: ''
                    };

                    this.$emit('createdSegment');
                });
            },

            deleteFilter(index) {
                this.segment.filters.splice(index, 1);

                console.log(this.segment.filters);
            }
        },

        created() {
            this.getFilters();
        }
    }
</script>

<style scoped>
    .btn-close{
        border: none;
        outline: none;
    }
    .replies-wrapper{
        font-family: "RalewayLight", "Helvetica Neue", Helvetica, Arial, sans-serif !important;
    }
    .btn-add-filter{
        margin-left: 30px;
        padding: 10px;
        display: block;
        border: none;
        background-color: transparent;
        outline: none;
        position: relative;
    }
    .btn-add-filter:before{
        content: '';
        position: absolute;
        left: -30px;
        top: 5px;
        border: 2px solid black;
        border-radius: 50%;
        height: 30px;
        width: 30px;
    }
    .btn-add-filter:after{
        content: '+';
        position: absolute;
        left: -24px;
        top: -8px;
        font-size: 40px;
    }
    .filter-segment{
        padding: 15px;
        margin: 0 -10px;
        margin-bottom: 10px;
        background-color: #dedede;
        border-radius: 10px;
        position: relative;
    }
    .filter-segment .btn-close{
        cursor: pointer;
        transition: all .3s;
        position: absolute;
        right: 15px;
        top: 22px;
        font-weight: bold;
        font-size: 20px;
        background-color: transparent;
    }
    .filter-segment .btn-close:hover{
        color: red;
    }
    .filter-segment span{
        margin-left: 10px;
    }
    .segment-input{
        border: none;
        border-radius: 7px;
        padding: 8px 10px;
        margin-bottom: 15px;
        width: 100%;
        outline: none;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
    }
    .btn-add-segment{
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
    .btn-add-segment:hover{
        border-color: #00b7f4;
        color: #00b7f4;
        background-color: #fff;
        box-shadow: 0 10px 6px #dededf;
    }
</style>
