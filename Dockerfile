FROM prestashop/prestashop:1.6.1.4

MAINTAINER Johan PROTIN <jprotin@hipay.com>

COPY conf /tmp
COPY src /var/www/html/modules
RUN sed -i "/exec apache2 -DFOREGROUND/d" /tmp/docker_run.sh \
    && sed -i "/Almost ! Starting Apache now/d" /tmp/docker_run.sh \
    	&& mv /tmp/hipay_install.php /var/www/html

ENTRYPOINT ["/tmp/entrypoint.sh"]