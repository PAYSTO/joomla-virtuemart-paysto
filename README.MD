### Instructions for installing and configuring the module Paysto Joomla + VirtueMart

Joomla: 3.3+
VirtueMart: 3.0+ 

Demo: http://joomla.ultraweb.ru

1. Download the archive from the repository as a zip file, if the callbacks do not work and the order status does not change,
   it means that the _SERVER variable is not configured correctly on your server; it does not issue REMOTE_ADDR correctly, then you
   it is necessary to make downloads of the module from the repository but already from the "update" branch.
1. In Joomla Administrator -> Extensions -> Extensions Manager **yoursite.ru**/administrator/index.php?option=com_installer install the downloaded archive here;
1. In methods of payment, add a method and select Paysto.
yoursite.ru/administrator/index.php?option=com_virtuemart&view=paymentmethod
1. Click the create button and create a new payment method. 
	- In the title: enter Paysto;
	- Nickname: paysto;
	- Payment method: choose Paysto;
1. In the VirtueMart section choose payment methods
yoursite.ru/administrator/index.php?option=com_virtuemart&view=paymentmethod
1. Choose the created method Paysto 
1. And go to the configuration tab.
	- Enter your data from your Paysto account in the store ID and Secret Key fields.
2. Turn on the module
3. We accept payments

**Attention! Confirm that your store transmits the request in Russian rubles.**

------------

### Инструкция по установке и настройке модуля Paysto Joomla + VirtueMart
Joomla: 3.3+
VirtueMart: 3.0+ 

Demo: http://joomla.ultraweb.ru

1. Скачиваем  архив с репозитория как zip файл при этом, если не работают обратные вызовы и у заказа не меняется статус, 
   значит на вашем сервере неправильно настроена переменная _SERVER она не выдает REMOTE_ADDR правильно, тогда вам 
   необходимо произвести скачивания модуля из репозитория но уже с ветки update.
1. В Joomla Administrator -> Расширения -> Менеджер расширений ваш_сайт.ру/administrator/index.php?option=com_installer устанавливаем тут скачанный архив;
1. В способах оплаты добавить способ и выбрать Paysto.
ваш_сайт.ру/administrator/index.php?option=com_virtuemart&view=paymentmethod
1. Нажимаете кнопку создать и создаете новый метод оплаты.
	- В названии — вводите Paysto
	- Псевдоним — paysto
	- Способ оплаты — выбираете Paysto
1. В разделе VirtueMart выбираете способы оплаты
ваш_сайт.ру/administrator/index.php?option=com_virtuemart&view=paymentmethod
1. Выбираете созданный метод Paysto
1. Переходим во вкладку конфигурация.
Введите в поля ID магазина и Secret Key свои данные из  личного кабинета Paysto.

2. Включаем модуль
3. Принимаем оплату

**Внимание! Убедитесь, что ваш магазин передает запрос в российских рублях.**
