FROM prestashop/prestashop:1.6.1.16

MAINTAINER support.wallet <support.tpp@hipay.com>

RUN apt-get update \
        && apt-get install -y ssmtp vim git cron \
                && curl -sS https://getcomposer.org/installer | php -- --filename=composer -- --install-dir=/usr/local/bin \
                && echo "sendmail_path = /usr/sbin/ssmtp -t" > /usr/local/etc/php/conf.d/sendmail.ini \
                && echo '' && pecl install xdebug-2.5.0 \
                && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
                && echo "mailhub=smtp:1025\nUseTLS=NO\nFromLineOverride=YES" > /etc/ssmtp/ssmtp.conf \
                &&  rm -rf /var/lib/apt/lists/*

COPY conf /tmp

COPY src /var/www/html/modules

RUN sed -i "/exec apache2-foreground/d" /tmp/docker_run.sh \
    && sed -i "/Almost ! Starting Apache now/d" /tmp/docker_run.sh \
        && chmod 777 -R /tmp

ENTRYPOINT ["/tmp/entrypoint.sh"]
