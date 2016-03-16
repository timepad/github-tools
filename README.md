# Как сгенерить релизноутсы

```
php make.php make_notes \
  --github_token "ваш_гитхаб_токен" \
  --github_user "timepad" \
  --github_repo "otp" \
  --from_tag "тег_с_которого_генерить" \
  --repo "полный_путь_до_папки_с_репозиторием"
```

Файл будет помещен в папку out.
  
Рекомендую сразу команду завернуть в sh-скрипт, например `make_timepad.sh`:

```
#!/bin/sh
php make.php make_notes \
  --github_token "ваш_гитхаб_токен" \
  --github_user "timepad" \
  --github_repo "otp" \
  --from_tag $1 \
  --repo "полный_путь_до_папки_с_репозиторием"
mate out/timepad_otp.md
```

Скрипт можно и нужно положить тут (`*.sh` в гитигноре). 
Вместо `mate` можно использовать другой любимый быстрый редактор текста, или что-нибудь, копирующее все в буфер обмена.

Использовать такой скрипт можно так:
 
```
chmod +x make_timepad.sh
./make_timepad.sh 4.367
```

## Отправка на почту
Теперь можно всё сразу выслать на почту. 
Для этого понадобится указать много доп параметров (рекомендация использовать .sh-скрипты остаётся в силе!)

```
#!/bin/sh
php make.php make_notes \
  --github_token "ваш_гитхаб_токен" \
  --github_user "timepad" \
  --github_repo "otp" \
  --from_tag $1 \
  --repo "полный_путь_до_папки_с_репозиторием" \
  --title "TimePad PHP" \
  --mail_to "releases@timepad.ru" \
  --mail_from "TimePad docs@timepad.ru" \
  --postmark_api "ключ_апи_постмарка"
```

* `title` — фрагмент темы письма: "Релиз(ы) **%title%** 123.0-123.2"
* `mail_from` — отправитель. Должен быть настроен в постмарке, иначе ничего не получится