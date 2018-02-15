<template>
    <div class="container"> 
        <div class="row">
            <div v-show="!isIframe || widgetOpen" class="webbot-container" :class="(isIframe) ? 'in-iframe' : ''" :style="widgetStyles">
                <div class="webbot-wrapper">
                    <div class="webbot-header" v-bind:style="{ 'background': bgColor }">
                        <div class="head-text">Борис Бот</div>
                        <div class="webbot-question">
                            <svg xmlns="http://www.w3.org/2000/svg" width="23" height="22" viewBox="0 0 23 22">
                                <path d="M1575.5,139a11.011,11.011,0,1,0,11.5,11A11.271,11.271,0,0,0,1575.5,139Zm0,21.154A10.164,10.164,0,1,1,1586.12,150,10.41,10.41,0,0,1,1575.5,160.154Zm0-5.5a0.433,0.433,0,0,0-.44.423v0.846a0.44,0.44,0,0,0,.88,0v-0.846A0.433,0.433,0,0,0,1575.5,154.654Zm0.05-11.846h-0.05a3.561,3.561,0,0,0-2.48.975,3.29,3.29,0,0,0-1.06,2.409,0.446,0.446,0,0,0,.89,0,2.464,2.464,0,0,1,.79-1.807,2.679,2.679,0,0,1,1.9-.731,2.643,2.643,0,0,1,2.61,2.5,2.488,2.488,0,0,1-1.21,2.171,3.983,3.983,0,0,0-1.88,3.432v1.2a0.44,0.44,0,0,0,.88,0v-1.2a3.2,3.2,0,0,1,1.48-2.721,3.331,3.331,0,0,0,1.62-2.894A3.49,3.49,0,0,0,1575.55,142.808Z" transform="translate(-1564 -139)"/>
                            </svg>
                        </div>
                        <div class="webbot-basket">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="20" viewBox="0 0 26 20">
                                <path d="M1618.89,145.321a0.58,0.58,0,0,0-.44-0.2h-0.01l-18.69-.05-1.32-3.713a0.561,0.561,0,0,0-.53-0.355h-4.35a0.521,0.521,0,1,0,0,1.04h3.96l4.74,13.309a0.538,0.538,0,0,0,.52.355h14.31v2.67h-1.06a2.088,2.088,0,0,0-1.94-1.266,2.065,2.065,0,0,0-1.94,1.266h-5.63a2.065,2.065,0,0,0-1.94-1.266,1.95,1.95,0,1,0,0,3.892,2.05,2.05,0,0,0,2.04-1.586h5.43a2.1,2.1,0,0,0,4.07,0h1.53a0.538,0.538,0,0,0,.55-0.52v-3.71a0.537,0.537,0,0,0-.55-0.52h-1.31l2.65-8.885A0.5,0.5,0,0,0,1618.89,145.321Zm-14.32,14.639a0.908,0.908,0,1,1,.97-0.906A0.935,0.935,0,0,1,1604.57,159.96Zm9.51,0a0.907,0.907,0,1,1,.95-1.034,0.545,0.545,0,0,1,.01.128A0.935,0.935,0,0,1,1614.08,159.96Zm1.1-5.3h-12.01l-3.05-8.554,17.59,0.055Z" transform="translate(-1593 -141)"/>
                            </svg>
                        </div>
                        <div class="webbot-close" @click="toggleWidget" v-if="isIframe">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18">
                                <path d="M1656,144.043L1654.96,143l-7.96,7.957L1639.04,143l-1.04,1.043,7.96,7.957-7.96,7.957,1.04,1.043,7.96-7.956,7.96,7.956,1.04-1.043L1648.04,152Z" transform="translate(-1638 -143)"/>
                            </svg>
                        </div>
                    </div><!-- End of .webbot-header -->
                    <div class="panel-body">
                        <div class="messages-wrapper"> 
                            <ul v-if="messages.length" class="messages-list">
                                <li v-for="message in messages" class="message clearfix">
                                    <div class="media-body" :class="(message.type === 'text-my') ? 'message-my' : 'message-bot'">
                                        <div class="message-img-wrapper" v-if="message.image">
                                            <div class="message-img">
                                                <img :src="message.image" :alt="message.text">
                                            </div>
                                        </div>
                                        <div class="message-text" v-if="message.text || message.type === 'action_typing'" v-bind:style="{ 'background': message.type === 'text-my'? bgColor: '#f0efef' }">
                                            <p v-if="message.text">{{ message.text }}</p>
                                            <div v-else-if="message.type === 'action_typing'" class="loader ball-beat">
                                                <div></div> 
                                                <div></div>
                                                <div></div>
                                            </div>
                                            <div class='message-tail'>
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="25" height="24" viewBox="0 0 25 24">
                                                    <g><path v-bind:style="{ 'fill': message.type === 'text-my'? bgColor: '#f0efef' }" d="M19,2a11.921,11.921,0,0,1-6.86,7.5c-5.8,2.317-8.38,1.5-8.38,1.5l16,6"/></g>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="message-buttons-bot media-body" v-if="message.type === 'social_share'">
                                        <ul class="buttons-list">
                                            <li class="btn-width btn-width-2">
                                                <button class="btn btn-default btn-sm" @click="SocialPopup('https://www.facebook.com/sharer/sharer.php?u='+message.url)">
                                                    <i class="fa fa-facebook"></i>
                                                </button>
                                            </li><li class="btn-width btn-width-2">
                                                <button class="btn btn-default btn-sm" @click="SocialPopup('http://vk.com/share.php?url='+message.url)">
                                                    <i class="fa fa-vk"></i>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="message-buttons-bot media-body" v-if="message.buttons && message.buttons.length">
                                        <ul class="buttons-list">
                                            <li v-for="button in message.buttons" :class="('width' in button) ? ('btn-width btn-width-' + button.width) : '1'">

                                                <a v-if="button.link" class="btn btn-default btn-sm" target="_blank" v-bind:style="{ 'color': bgColor, 'border-color': bgColor }" @click="openInMessenger($event, button)">{{ button.text }}</a>

                                                <button v-else-if="button.checkbox" class="btn btn-default btn-sm btn-checkbox" v-bind:style="{ 'color': button.params.checked ? '#fff' : bgColor, 'background-color': button.params.checked ? bgColor : '#fff','border-color': bgColor }" :class="{checked: button.params.checked}" @click="toggleCheckbox($event, button, message)">{{ button.text }}</button>

                                                <button v-else class="btn btn-default btn-sm" v-bind:style="{ 'color': bgColor, 'border-color': bgColor }" @click="sendMessage($event, button)">{{ button.text }}</button>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            </ul>
                            <div v-else-if="!startMessagesReady" class="loader ball-beat">
                                <div></div>
                                <div></div>
                                <div></div> 
                            </div>
                        </div><!-- End of .messages-wrapper -->
                        <div v-if="respondent && showHrefMessage" class="surveybot-free-href">provide for free <a href="https://borisbot.com" target="_blank">Surveybot</a></div>
                    </div><!-- End of .panel-body -->
                    <div class="send-message">
                        <div class="send-message-visible">
                            <div class="send-message-block send-message-menu" @click="toggleMenu">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="13" viewBox="0 0 16 13">                            
                                <path v-bind:style="{ 'fill': bgColor }" d="M1323.6,677.529h14.8a0.787,0.787,0,0,0,0-1.529h-14.8A0.787,0.787,0,0,0,1323.6,677.529Zm0,5.736h14.8a0.788,0.788,0,0,0,0-1.53h-14.8A0.788,0.788,0,0,0,1323.6,683.265Zm0,5.735h14.8a0.787,0.787,0,0,0,0-1.529h-14.8A0.787,0.787,0,0,0,1323.6,689Z" transform="translate(-1323 -676)"/>
                              </svg>
                            </div>
                            <div class="send-message-block input-group">
                                <input type="text" class="send-message-input" placeholder="Введите сообщение..." v-model="text" @keyup="toggleButSend($event)">
                            </div>
                            <div class="send-message-block send-message-emo" style="display:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                    <path v-bind:style="{ 'fill': bgColor }" d="M1642.66,674.34a8,8,0,1,0,0,11.316A8.014,8.014,0,0,0,1642.66,674.34Zm-0.87,10.444a6.771,6.771,0,1,1,0-9.572A6.787,6.787,0,0,1,1641.79,684.784Zm-7.78-6.952a0.94,0.94,0,1,1,.94.935A0.937,0.937,0,0,1,1634.01,677.832Zm4.25,0a0.935,0.935,0,1,1,.93.935A0.935,0.935,0,0,1,1638.26,677.832Zm2.21,3.831a3.828,3.828,0,0,1-6.94-.012,0.448,0.448,0,0,1,.24-0.591,0.5,0.5,0,0,1,.18-0.034,0.47,0.47,0,0,1,.42.279,2.791,2.791,0,0,0,2.64,1.659,2.838,2.838,0,0,0,2.63-1.66,0.446,0.446,0,0,1,.59-0.236A0.453,0.453,0,0,1,1640.47,681.663Z" transform="translate(-1629 -672)"/>
                                </svg>
                            </div>
                            <div class="send-message-block send-message-pict" style="display:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15">                           
                                    <path v-bind:style="{ 'fill': bgColor }" d="M1609,685.418a1.582,1.582,0,0,0,1.58,1.582h11.84a1.584,1.584,0,0,0,1.58-1.582V673.582a1.582,1.582,0,0,0-1.58-1.582h-11.84a1.584,1.584,0,0,0-1.58,1.582v11.836Zm13.42,0.831h-11.84a0.831,0.831,0,0,1-.83-0.831v-1.976l2.85-2.845,2.43,2.431a0.374,0.374,0,0,0,.53,0l4.39-4.389,3.3,3.3v3.475A0.831,0.831,0,0,1,1622.42,686.249Zm-11.84-13.5h11.84a0.831,0.831,0,0,1,.83.831v7.3l-3.04-3.037a0.374,0.374,0,0,0-.53,0l-4.39,4.389-2.43-2.43a0.372,0.372,0,0,0-.53,0l-2.58,2.577v-8.8A0.831,0.831,0,0,1,1610.58,672.751Zm3.07,5.259a1.91,1.91,0,1,0-1.91-1.909A1.915,1.915,0,0,0,1613.65,678.01Zm0-3.068a1.159,1.159,0,1,1-1.16,1.159A1.161,1.161,0,0,1,1613.65,674.942Z" transform="translate(-1609 -672)"/>
                                </svg>
                            </div>
                            <div class="send-message-block send-message-trig-style" @click="sendMessage($event)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 25 25">                                
                                <circle v-bind:style="{ 'fill': bgColor }" class="cls-1" cx="12.5" cy="12.5" r="12.5"/>
                                <ellipse v-bind:style="{ 'fill': bgColor }" class="cls-2" cx="12.69" cy="11.938" rx="6.25" ry="5.218"/>
                                <path v-bind:style="{ 'fill': bgColor }" class="cls-3" d="M1552.67,764.448s0.2-.28-0.1,3.042c-0.08.76,2.59-1.778,2.59-1.778" transform="translate(-1544 -751)"/>
                              </svg>
                            </div>
                            <div class="send-message-block send-message-ok">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">  
                                    <path v-bind:style="{ 'fill': bgColor }" d="M1647,678.333L1639.22,669v5.334c-7.78,1.333-11.11,8-12.22,14.666,2.78-4.667,6.67-6.8,12.22-6.8v5.467Z" transform="translate(-1627 -669)"/>
                                </svg>
                            </div>
                        </div>
                        <div class="send-message-menu-but">
                            <ul class="buttons-list" v-if="persistentMenu.length">
                                <li v-for="button in persistentMenu" :class="('width' in button) ? ('btn-width btn-width-' + button.width) : '1'"> 
                                    <a v-if="button.link" :href="button.link" class="btn btn-default btn-sm" v-bind:style="{ 'color': bgColor, 'border-color': bgColor }" target="_blank">{{ button.text }}</a>
                                    <button v-else class="btn btn-default btn-sm" v-bind:style="{ 'color': bgColor, 'border-color': bgColor }" @click="sendMessage($event, button)">{{ button.text }}</button>
                                </li>
                            </ul>
                        </div>
                    </div><!-- End of .send-message -->
                </div><!-- End of .webbot-wrapper -->
                <div class="toggle-widget" v-if="false">
                    <div class="toggle-widget-tail">
                        <svg xmlns="http://www.w3.org/2000/svg" width="88.88" height="30.25" viewBox="0 0 88.88 30.25">
                            <path d="M1519.53,707s25.06,14.941,38.43,19.2a197.55,197.55,0,0,0,44.19,8.8s-14.88-7.493-21.29-16.39c-0.28-.4-0.55-0.8-0.8-1.206-4.01-6.456,2.88-10.4,2.88-10.4" transform="translate(-1518.5 -706.5)"/>
                        </svg>
                    </div>
                </div>
            </div><!-- End of .webbot-container -->

            <!-- Триггеры -->
            <triggers v-show="isIframe && !widgetOpen && (triggersList.length !== 0)" :triggersList="triggersList" :style="widgetStyles" :bgColor="bgColor" @closeTriggers="closeTriggers" @toggleWidget="toggleWidget" @sendMessage="sendMessage" @openInMessenger="openInMessenger(arguments[0], arguments[1])"></triggers>

        </div><!-- End of .row -->
    </div><!-- End of .container -->
</template>
<script>
    import { mapActions, mapGetters } from 'vuex'
    import Echo from "laravel-echo"
    import Pusher from "pusher-js"
    import BFPp from "fingerprintjs2"
    import CryptoJS from "crypto-js"
    import XD from '../../helpers/XD'

    Pusher.logToConsole = false; // логирование

    Vue.component('triggers', require('./Triggers.vue'));

    export default {
        data () {
            return  {
                Echo: null,
                parentUrl: null,
                respondent: null,
                conversationId: null,
                showDownMenu: false,
                messages: [],
                existingMessages: [],
                existingDialogs: [],
                startMessagesReady: false,
                persistentMenu: [],
                text: '',
                timeCreated: 0,
                timeRendered: 0,
                triggersQueue: [],
                triggersList: [],
                triggersReplied: [],
                messageWaitingReply: null,
                widgetOpen: false,
                widgetHeight: 0,
                widgetWidth: 0,
                widgetRight: 16,
                widgetBottom: 110,
                widgetTop: null,
                widgetLeft: null,
                showHrefMessage: true,
                openedWindows: {},
            };
        },
        props: ['latlng', 'projectId', 'widgetId', 'surveyId', 'widgetJson', 'serverTimeout', 'startData'],
        computed: {
            // см. resources/assets/js/store/modules/* (vuex)
            ...mapGetters([
                'currentSurvey',
                'triggers',
                'projectEvents',
                'loadingSurvey'
            ]),
            privateChannel () {
                return this.Echo.connector.pusher.channels.find('private-Webbot.Conversation.' + this.conversationId);
            },
            isIframe () {
                return this.parentUrl ? true : false;
            },
            widget () {
                return $.parseJSON(this.widgetJson);
            },
            bgColor () {
                return this.widget.bg_color;
            },
            parentBaseUrl () {
                let a = window.document.createElement('a');
                a.href = this.parentUrl;
                return a.origin;
            },
            widgetStyles () {
                if (!this.isIframe || (!this.widgetOpen && this.triggersList.length === 0))
                    return null;

                let styles = {
                    height: this.widgetHeight + 'px',
                    width: this.widgetWidth + 'px',
                    right: this.widgetRight + 'px',
                    bottom: this.widgetBottom + 'px',
                    // top: this.widgetBottom === 0 ? 0 : 'auto',
                    // left: this.widgetRight === 0 ? 0 : 'auto',
                };

                return styles;
            }
        },
        methods: {
            // см. resources/assets/js/store/modules/* (vuex)
            ...mapActions([
                'getSurvey',
                'getWidget',
                'getTriggers',
                'getProjectEvents',
                'markEventAsReplied',
                'saveTimelog'
            ]),
            // Возвращает отпечаток браузера
            getFingerprint () {            
                return new Promise((resolve, reject) => {
                    new BFPp({
                        excludeAdBlock: true,
                        excludeJsFonts: true,
                        excludeFlashFonts: true
                    }).get((result, components) => {
                        resolve({result, components});       
                    });
                });
            },
            // Возвращает более точный отпечаток с местоположением пользователя (используется как id диалога с пользователем)
            getConversationId (result) {       
                return new Promise((resolve, reject) => {
                    let conversationId = null;

                    conversationId = CryptoJS.SHA1(result + this.latlng + this.surveyId).toString();
                    return resolve(conversationId);
                });
            },
            // Вызов события из App/Events/*
            triggerEvent(eventName, data) {
                let success = false;
                return new Promise((resolve, reject) => {
                    success = this.privateChannel.trigger(eventName, data);
                    if (success)
                        resolve({eventName, data});
                    else
                        reject('Ошибка при вызове события ' + eventName);
                });
            },
            // Переключение "чекбоксов"
            toggleCheckbox (event, button, message) {
                let refStep = button.params.ref_step,
                    refButton = message.buttons.find((btn) => {
                        return btn.params.step == refStep && btn.params.checked_answers;
                    }),
                    checkedButtons = [];

                button.params.checked = !button.params.checked;
                
                checkedButtons = message.buttons.filter((btn) => {
                    return btn.params.checked;
                });

                Vue.set(refButton.params, 'checked_answers', _.map(checkedButtons, (btn) => {
                    return btn.params.order;
                }));
            },
            // Отправка сообщения
            sendMessage (event, button = null, text=null) {
                
                if(text){this.text = text;}
                
                if (!this.text && !button)
                    return;

                if (button) {
                    
                    if (button.button_type && button.button_type === 'start_survey') {
                        yaCounter43586749.reachGoal("start_survey");  
                        ga('send', {
                            hitType: 'event',
                            eventCategory: 'survey',
                            eventAction: 'start'
                        });
                    }
                    
                    if (button.checkbox) // Если нажат чекбокс
                        return;
                    if (button.params && button.params.checked_answers && button.params.checked_answers.length === 0) // Если не выбрано ни одного чекбокса
                        return;

                    this.text = "/" + button.command;
                    
                } else if (this.messageWaitingReply && this.messageWaitingReply.event_id) {

                    this.triggersReplied.push(this.messageWaitingReply.trigger_id);

                    this.markEventAsReplied({
                        id: this.messageWaitingReply.event_id,
                        data: {respondent_id: this.respondent.id},
                    }).then((response) => {
                        this.messageWaitingReply = null;
                    });
                }

                let type = (this.text.substring(0, 1) === "/") ? 'command' : 'text-my',
                    params = button && button.params ? button.params : {};

                // this.messages.push({text: button ? button.text : this.text, type: 'text-my'});

                // Имитация что бот печатает ответное сообщение
                this.messages.push({text: '', type: 'action_typing'});
                this.scrollDown();

                let message = {
                    text_body: button ? button.text : this.text,
                    text: this.text,
                    type: type,
                    widget_id: this.widgetId,
                    survey_id: this.surveyId,
                    conversation_id: this.conversationId,
                    respondent_id: this.respondent.id,
                    params: params
                };

                // Вызов события client-MessageWasSended (обрабатывается в App/Http/Controllers/Bot/Web/WebhookController.php)
                this.triggerEvent('client-MessageWasSended', {
                    message: message
                }).then(({eventName, data}) => {
                    this.text = '';
                    this.scrollDown();                   
                    this.setBgColor();                      
                    $('.send-message-ok').css('display','none');
                }).catch((error) => {
                    alert(error);
                });
            },
            openInMessenger(event, button){

                if (button.button_type && button.button_type === 'start_survey') {
                        yaCounter43586749.reachGoal("start_survey");  
                        ga('send', {
                            hitType: 'event',
                            eventCategory: 'survey',
                            eventAction: 'start'
                        });
                }

                if (button.link) {
                    if (button.params && button.params.type === 'link_respondent') {
                        let event = {};

                        let buttonTemp = {
                            command: '/getTextChannelsConnect',
                        };

                        this.sendMessage(event, buttonTemp);

                        setTimeout(function() {
                            window.open(button.link); // chrome блокирует всплывающее окно
                        }, 2000);
                    } else if (button.params && button.params.type === 'sa') { // social_auth
                        event.preventDefault();
                        return this.socialAuthPopup(button);
                    } else {
                        window.open(button.link);
                    }
                }
            },
            scrollDown () {                
                let messagesBlock = document.getElementsByClassName('messages-wrapper')[0];
                $('.messages-wrapper').animate({scrollTop: messagesBlock.scrollHeight}, 400); 
            },
            setMessagesHeight(){
                let menuHight = document.getElementsByClassName('send-message')[0].offsetHeight - 54;
                let messageHeight = ((window.innerHeight - 108) > 530)? 530 : (window.innerHeight - 108);
                
                if(this.showDownMenu){messageHeight -=menuHight;}               
                
                let messagesBlock = document.getElementsByClassName('messages-wrapper')[0];
                
                messagesBlock.style.maxHeight = (messageHeight)+'px';  
                messagesBlock.style.height = (messageHeight)+'px'; 
            },
            toggleMenu(){
                this.showDownMenu = !this.showDownMenu;
                $('.send-message-menu-but').toggle();
                this.setMessagesHeight();
            },
            toggleButSend(event) {
                if(event.key == "Enter") {
                    this.sendMessage(event);
                } 
                //else {                
                //    if (!this.text){$('.send-message-ok').css('display','none');}
                //    else {$('.send-message-ok').css('display','block');}
                //}
            },
            toggleWidget () {
                if (this.isIframe) {
                    this.sendParent("close");
                }
            },
            closeTriggers(){
                if (this.isIframe) {
                    this.triggersList = [];
                    this.sendParent("closetriggers");
                }
            },
            sendParent(msg) {
                if (this.isIframe)
                    XD.postMessage(msg, this.parentUrl, parent);
            },
            setBotMessage (message) {
                let timeout = 50,
                    newMessage = null;

                if (message.action && message.action === 'close_social_auth') {
                    if (this.openedWindows.social_auth && !this.openedWindows.social_auth.closed) {
                        this.openedWindows.social_auth.close();
                        delete this.openedWindows.social_auth;
                    }

                    // alert('Регистрация прошла успешно! Теперь вы можете продолжить опрос.');

                    this.messages.push({text: 'Регистрация прошла успешно!', type: 'text-bot', buttons: null});
                }

                this.messages = this.messages.filter((m, index) => {
                    return m.type !== 'action_typing';
                });

                // Дублирование диалогов в окошко триггерных сообщений
                if (this.isIframe && (message.type === 'trigger' || 
                    (message.type !== 'trigger' && !this.widgetOpen))) {

                    if (message.waiting_reply) {
                        this.messageWaitingReply = message;
                    }

                    if (message.image && !this.widgetOpen) {
                        this.closeTriggers();
                        this.toggleWidget();
                    } else {
                        this.triggersList.push(message);
                    }
                }

                return new Promise((resolve, reject) => {

                    newMessage = {
                        text: message.text,
                        type: message.type,
                        url: message.url,
                        buttons: message.buttons
                    };

                    if (message.image) {
                        newMessage.image = message.image;
                        timeout = 600;
                    }

                    if (newMessage.type !== 'empty' && newMessage.type !== 'trigger') {
                        this.messages.push(newMessage);
                    }

                    resolve({newMessage, timeout});
                });
            },
            setBgColor(){
                var bg_color = this.widget.bg_color;
                
                $(".buttons-list li .btn:not(.btn-checkbox)").hover(function(e) { 
                    $(this).css("background-color",e.type === "mouseenter"? bg_color: "#fff");
                    $(this).css("color",e.type === "mouseenter"? "#fff" : bg_color); 
                });
                $(".buttons-list li .btn:not(.btn-checkbox)").focus(function(e) { 
                    $(this).css("background-color",e.type === "mouseenter"? "#fff" : bg_color); 
                    $(this).css("color",e.type === "mouseenter"? bg_color : "#fff"); 
                });    
                
            },
            socialAuthPopup (button) {
                this.openedWindows.social_auth = window.open(button.link,'','toolbar=0,status=0,width=626,height=436');

                this.messages.push({text: button.text, type: 'text-my'});

                this.scrollDown();

                return false;
            },
            SocialPopup(url,button_text) {

                window.open(url,'','toolbar=0,status=0,width=626,height=436');
                
                this.messages.push({text: button_text, type: 'text-my'});

                // Имитация что бот печатает ответное сообщение
                this.messages.push({text: '', type: 'action_typing'});
                this.scrollDown();
                
                this.triggerEvent('client-MessageWasSended', {
                    message: {
                        text: button_text,
                        type: 'text-my',
                        widget_id: this.widgetId,
                        conversation_id: this.conversationId,
                        respondent_id: this.respondent.id,
                        params: {}
                    }
                }).then(({eventName, data}) => {
                    this.scrollDown();                   
                    this.setBgColor();                      
                }).catch((error) => {
                    alert(error);
                });
            },
            ToggleHrefMessage(){
                let messagesBlock = document.getElementsByClassName('messages-wrapper')[0];
                if(messagesBlock.scrollTop > (messagesBlock.scrollHeight - messagesBlock.clientHeight -20)){
                    this.showHrefMessage = true;
                } else {
                    this.showHrefMessage = false;
                }
            },
            addExistingDialogs () {
                return new Promise((resolve, reject) => {
                    if (!this.existingDialogs.length)
                        return resolve(false)
                    
                    this.existingDialogs.forEach((message, index) => {
                        this.setBotMessage(message).then((response) => {
                            if ((index + 1) < this.existingDialogs.length) {
                                // this.messages.push({text: '', type: 'action_typing'});
                            } else {
                                this.scrollDown();
                                this.existingDialogs = [];

                                resolve(true)
                            }
                        });
                    });
                });
            },
            addExistingMessages () {
                return new Promise((resolve, reject) => {
                    if (!this.existingMessages.length)
                        return resolve(false)

                    this.existingMessages.forEach((message, index) => {
                        this.setBotMessage(message).then((response) => {
                            if ((index + 1) < this.existingMessages.length) {
                                // this.messages.push({text: '', type: 'action_typing'});
                            } else {
                                this.scrollDown();
                                this.existingMessages = [];

                                resolve(true)
                            }
                        });
                    });
                });
            }
        },
        created () {
            this.timeCreated = Date.now();

            // Установка стартовых сообщений и нижнего меню
            let startData = $.parseJSON(this.startData);
            this.persistentMenu = startData.persistent_menu;

            if (startData.messages && startData.messages.length) {
                startData.messages.forEach((msg, index) => {
                    setTimeout(() => {
                        this.messages.push({text: '', type: 'action_typing'});
                        this.setBotMessage(msg).then((response) => {
                            if ((index + 1) < startData.messages.length) {
                                this.messages.push({text: '', type: 'action_typing'});
                            } else {
                                this.startMessagesReady = true; // все стартовые сообщения отобразились в чате
                                this.scrollDown();
                            }
                        });
                    }, (index + 1) * 1000);
                });
            } else {
                this.startMessagesReady = true;
            }

            // Получаем отпечаток браузера
            this.getFingerprint().then(({result, components}) => {
                this.Echo = new Echo({
                    broadcaster: 'pusher',
                    key: window.Laravel.pusherKey,
                    cluster: 'eu',
                    encrypted: true,
                    namespace: 'App.Events.Webbot',
                    authTransport: 'ajax',
                    enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling', 'sockjs'],
                    authEndpoint: '/pusher/auth/private',
                    auth: {
                        params: {
                            fp: result,
                            widget_id: this.widgetId,
                            survey_id: this.surveyId
                        },
                        headers: {
                            'X-CSRF-Token': ''
                        }
                    }
                });

                // подписываемся на канал присутствия
                this.Echo.join('conversationPresenceChannel');

                // Получаем id диалога с пользователем
                this.getConversationId(result).then((conversationId) => {
                    this.conversationId = conversationId;

                    // Подписываемся на пользователей которые входят в чат виджета (widgetId)
                    this.Echo.join('Webbot.Chat.' + this.widgetId + '.' + this.surveyId)
                        .here((users, data) => {
                            users.forEach((user) => {

                                // Находим текущего пользователя по отпечатку
                                if (!this.respondent && user.conversation_id === this.conversationId) {
                                    window.console.log('conversation: ', user);

                                    this.respondent = user;

                                    if (user.dialogs && user.dialogs.length) {
                                        this.existingDialogs = user.dialogs;
                                    }
                                    if (user.messages && user.messages.length) {
                                        this.existingMessages = user.messages;
                                    }

                                    // Замер времени
                                    let timeoutLogData = {};
                                    components.forEach(function(comp) {
                                        if (['user_agent', 'language', 'navigator_platform'].includes(comp.key))
                                            timeoutLogData[comp.key] = comp.value;
                                    });

                                    this.timeRendered = Date.now();

                                    timeoutLogData.timeout1 = Math.max(0, parseInt(this.serverTimeout));
                                    timeoutLogData.timeout2 = this.timeRendered - this.timeCreated;
                                    timeoutLogData.total_timeout = timeoutLogData.timeout1 + timeoutLogData.timeout2;

                                    // this.saveTimelog({respondentId: user.id, data: timeoutLogData});
      
                                    // Подписываемся на сообщения от бота
                                    this.Echo.private('Webbot.Conversation.' + this.conversationId)
                                        .listen('MessageWasSended', (e) => {

                                            // Получаем сообщение от бота и заменяем action_typing на полученное сообщение
                                            this.setBotMessage(e.message).then(({newMessage, timeout}) => {
                                                this.setBgColor();
                                                setTimeout(() => {
                                                    this.scrollDown();
                                                }, timeout);
                                            });
                                        });
                                }
                            });
                        });
                });
            });
        },
        watch: {
            startMessagesReady (ready) {

                // Добавление сообщений, которые были до перезагрузки страницы
                if (ready && this.existingMessages.length) {
                    this.addExistingMessages();
                }

                if (!ready || this.triggersQueue.length === 0)
                    return;

                // Добавление в чат триггерных сообщений, которые были в очереди
                this.triggersQueue.forEach((msg, index) => {
                    setTimeout(() => {
                        this.messages.push({text: '', type: 'action_typing'});
                        this.setBotMessage(msg).then((response) => {
                            if ((index + 1) < this.triggersQueue.length) {
                                this.messages.push({text: '', type: 'action_typing'});
                            } else {
                                this.startMessagesReady = true; // все стартовые сообщения отобразились в чате
                                this.scrollDown();
                            }

                            this.triggersQueue.splice(index, 1);
                        });
                    }, (index + 1) * 1000);
                });
            },
            widgetOpen: {
                handler: function (val, oldVal) {
                    var scrollDown = this.scrollDown;
                    if(val){    
                        setTimeout(function() {scrollDown();},300);
                    }
                },
                deep: true
            }
            
        },
        mounted () {
            this.setMessagesHeight();           
            window.addEventListener('resize', this.setMessagesHeight);
            
            let messagesBlock = document.getElementsByClassName('messages-wrapper')[0];
            messagesBlock.addEventListener('scroll', this.ToggleHrefMessage);
            
            // Меняем цвета на заданные
            this.setBgColor(); 
            
            // Узнаем с какого адреса страница открыта в iframe
            this.parentUrl = decodeURIComponent(window.location.hash.toString().replace(/^#/, ''));

            // Получаем триггеры с бэка и отправляем их в скрипт виджета (surveybot.widget.js)
            if (this.parentUrl) {
                this.widgetOpen = false;

                this.getProjectEvents(this.projectId).then((response) => {
                    this.sendParent({triggers: this.projectEvents});
                });
                
                // Ожидаем триггерные сообщения или параметры виджета
                XD.receiveMessage((message) => {
                    if (message.data.styles !== undefined) {
                        for (let key in message.data.styles) {
                            if (message.data.styles.hasOwnProperty(key)) {
                                Vue.set(this, key, message.data.styles[key]);
                            }
                        }
                        return;
                    }

                    window.console.log('Chat.vue receiveMessage: ', message);

                    if (message.data.fireEvent !== undefined) {
                        let delay = !this.respondent ? 2000 : 4;
                        setTimeout(() => {
                            message.data.fireEvent.respondent_id = this.respondent.id;
                            this.triggerEvent('client-TriggerEventWasFired', {
                                message: message.data.fireEvent,
                            });
                        }, delay);
                        return;
                    }

                    if (message.data.widgetOpen !== undefined) {
                        return this.widgetOpen = message.data.widgetOpen;
                    }

                    if (message.data.triggerIsReached !== undefined && !this.widgetOpen) {
                        return this.closeTriggers();
                    }

                    if (!message.data.trigger) {
                        return;
                    }

                    let trigger = message.data.trigger;

                    if (trigger.action === 'start_survey') {

                        if (trigger.dont_repeat_after_reply && trigger.replied_respondents && trigger.replied_respondents.includes(this.respondent.id)) {
                            return;
                        }

                        if (trigger.dont_repeat_after_reply && this.triggersReplied.includes(trigger.id)) {
                            return;
                        }

                        let tEvent = trigger.currentEvent;

                        let delay = this.respondent ? 4 : 2800;

                        setTimeout(() => {
                            this.triggerEvent('client-MessageWasSended', {
                                message: {
                                    text_body: null,
                                    text: '/start_survey',
                                    type: 'command',
                                    widget_id: this.widgetId,
                                    survey_id: trigger.survey_id,
                                    conversation_id: this.conversationId,
                                    respondent_id: this.respondent.id,
                                    params: []
                                }
                            }).then(({eventName, data}) => {
                                this.text = '';
                                this.scrollDown();
                                this.setBgColor();
                                $('.send-message-ok').css('display','none');
                            }).catch((error) => {
                                alert(error);
                            });
                        }, delay);

                        return;
                    } // end if (trigger.action === 'start_survey')

                    // Триггерное сообщение получено
                    let triggerMessage = {
                        text: trigger.message_text,
                        type: 'trigger',
                        buttons: trigger.buttons || null
                    };

                    // Если стартовые сообщения еще не отобразились в чате, то ставим в очередь триггерное сообщение
                    if (!this.startMessagesReady) {
                        return this.triggersQueue.push(triggerMessage);
                    }

                    // Иначе сразу добавляем триггерное сообщение в чат
                    this.setBotMessage(triggerMessage).then((response) => {
                        this.scrollDown();
                    });

                }, this.parentBaseUrl);
            }
        },
        components: [
            'triggers'
        ],
    }
</script>
