## Trading
### addNewCandle
> початкова функція. Запускає почерзі всі інші функції. Після чого зберігає нові точки до таблиці графіків і дані торгівлі. І вертає змінений обʼєкт маркета назад в циклічну службу.
### addCandleData
> Додає нову свічку до списку свічок і видаляє останню, для того щоб не перевищувати розмір списку
### makeAnalis
> Робить аналіз RSI і стохастичний RSI
### getLastBalance
> Отримує попередні дані зі змінної, або з бази, або з налаштувань
### getStochRsiLogic
> Інтерпретує стохастичний графік чи є пересічення ліній вверх чи вниз. Прописує значення `up` якщо графік починає йти вверх, і `down`, якщо графік починає йти вниз
### makeTrade
> Збирає потрібні дані і відправляє їх в потібну функцію купівлі `onDeposit` чи продажу `onBought`
### onDeposit
> 1. задає початкову суму баланса
> 2. Якщо тогівля

>   2.1. бінанс купівля по маркету і розрахунок ціни з результату. Якщо треба профіт то створюється бінанс лімітний продаж по відносній профітній ціні і записує в таблицю ордерів

>   2.2. Імітація покупки по формулі
> 3. Якщо проторгувало без помилки то створити запис таблиці симуляції. Позначити маркер купівлі на графіку
### onBought
> 1. Якщо торгівля

>   1.1. Якщо включений профіт

>   1.1.1. Якщо останній лімітний ордер продажу завершений тоді вернутися до купівлі.

>   1.1.2. Якщо лімітний ордер незавершений в базі, то перевірити його статус в бінансі. Якщо після перевірки завершений, то йде підрахунок балансу і ставиться маркер продажу на графіку. 

>   1.1.3. Якщо відмінений ордер, тоді підраховує баланс, але це не веде до запису результату торгівлі.

>   1.2. Якщо треба продавати по аналітичним показникам RSI

>   1.2.1 бінанс продаж по маркету і розрахунок ціни з результату.
> 2. Якщо не торгівля тоді імітація продажу по формулі
> 3. Якщо проторгувало без помилок то створити запис таблиці симуляції. Позначити маркер продажу на графіку.


## TODO:
- [X] Додати купівлю до історії дозакупки як єдиний елемент і почати змінні прогресії з початку.
- [X] Дописати алгоритм дозакупки для торгівлі
- [X] Дописати алгоритм дозакупки для тестової торгівлі
- [X] Переписати продаж по профіту для дозакупки
- [X] Переписати продаж по індикаторам для дозакупки
- [X] Переписати продаж тестової торівлі для дозакупки
- [X] Протестувати чи робить дозакупка правильно
- [X] Додати поле про початкове значення дозакупки і використовувати його замість суми покупки, при дозакупці