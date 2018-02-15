# Часть кода проекта borisbot.com для конкурса Хакатон


## Общее описание:
В основе поиска собеседников лежит пересечение сегментов.
Под сегментом мы понимаем - совокупность различных фильтров с общим названием(название сегмента).
В свою очередь сегменты фильтруют всех респондентов по заданным в них фильтрах.
Под респондентом мы понимаем - юзера прошедшего опрос в нашей системе в одном из 4х ботов(телеграм, фейсбук, веббот, триггреный бот).
Имеется функционал для указания связей между двумя различными сегментами. Данная связь позволяет свести респондентов одного сегмента с репондентами другого сегмена в общем чате(комнате), который для них создается.

## Добавленный функционал:
- Итерфейс для создания сегментов аудиторий (комбинация фильтров)
- Создание пересечений сегментов
- Выявление пользователей которые пересекаются по интересам (сегментам) в процессе прохождения опроса
- Автоматическое создание чат комнат для пересекающихся сегментов пользователей
- Push-уведомления для пользователей по интресам (по каналам telegram, facebook, web, email)
