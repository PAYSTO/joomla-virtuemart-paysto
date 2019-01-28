Instructions for installing and configuring the module Paysto
Joomla v3.3 + VirtueMart 3.0.3 and higher
Installation:
1. In Joomla Administrator -> Extensions -> Extension Manager
yoursite.ru / administrator / index.php? option = com_installer
 
1.1 Install the downloaded archive from the Paysto site.
1.2 In the methods of payment, add a method and select Paysto.
yoursite.ru / administrator / index.php? option = com_virtuemart & view = paymentmethod
1.3 Click the create button and create a new payment method.
In the title - enter Paysto
Nickname - paysto
Payment method - choose Paysto

In the VirtueMart section choose payment methods
yoursite.ru / administrator / index.php? option = com_virtuemart & view = paymentmethod
Choose the created method Paysto
And go to the configuration tab.

Enter your data from your Paysto account in the store ID and Secret Key fields.
Register callbacks for example:
Payment notification POST request
Success redirect POST request
http: //VASHSITE.ru/index.php? option = com_virtuemart & view = pluginresponse & task = pluginresponsereceived
Failure redirect POST request
http: //VASHSITE.ru/index.php? option = com_virtuemart & view = pluginresponse & task = pluginUserPaymentCancel

1. To work with an online cash register: Add tax classes

2. Turn on the Paymaster module
 
3. Assign tax class to tax rate
 
4. Add all goods tax class


Инструкция по установке и настройке модуля Paysto
Joomla v3.3+VirtueMart 3.0.3 и выше
Установка:
1. В Joomla Administrator -> Расширения -> Менеджер расширений 
ваш_сайт.ру/administrator/index.php?option=com_installer 
 
1.1	Установить скачанный  архив с сайта Paysto.
1.2	 В способах оплаты добавить способ и выбрать Paysto.
ваш_сайт.ру/administrator/index.php?option=com_virtuemart&view=paymentmethod
1.3	 Нажимаете кнопку создать и создаете новый метод оплаты.
В названии — вводите Paysto
Псевдоним — paysto
Способ оплаты — выбираете Paysto

В разделе VirtueMart выбираете способы оплаты
ваш_сайт.ру/administrator/index.php?option=com_virtuemart&view=paymentmethod
Выбираете созданный метод Paysto
И переходите во вкладку конфигурация.

Введите в поля ID магазина и Secret Key свои данные из  личного кабинета Paysto.
Пропишите обратные вызовы по примеру:
Payment  notification   POST запрос 
Success redirect         POST запрос 
http://ВАШСАЙТ.ru/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived 
Failure redirect            POST запрос
http://ВАШСАЙТ.ru/index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel
 
1.	Для работы с онлайн-кассой: Добавляем налоговые классы 

2.	Включаем модуль Paymaster
 
3.	Присваиваем налоговый класс к налоговой ставке
 
4.	Добавляем всем товарам налоговый класс