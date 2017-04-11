#!/bin/sh -e

#=============================================================================
#  Use this script build hipay images and run Hipay Enterprise's containers
#
#==============================================================================
if [ "$1" = '' ] || [ "$1" = '--help' ];then
    printf "\n                                                                                  "
    printf "\n ================================================================================ "
    printf "\n                                  HiPay'S HELPER                                 "
    printf "\n                                                                                  "
    printf "\n For each commands, you may specify the prestashop version "16"           "
    printf "\n ================================================================================ "
    printf "\n                                                                                  "
    printf "\n                                                                                  "
    printf "\n      - init      : Build images and run containers (Delete existing volumes)     "
    printf "\n      - restart   : Run all containers if they already exist                      "
    printf "\n      - up        : Up containters                                                "
    printf "\n      - exec      : Bash prestashop.                                              "
    printf "\n      - log       : Log prestashop.                                               "
    printf "\n                                                                                  "
fi

if [ "$1" = 'init' ];then
    docker-compose stop
    docker-compose rm -fv
    rm -Rf web/
    docker-compose build --no-cache
    docker-compose up -d
fi

if [ "$1" = 'restart' ];then
    docker-compose stop
    docker-compose up -d
fi

if [ "$1" = 'up' ];then
    docker-compose up -d
fi

if [ "$1" = 'exec' ];then
    docker exec -it hipay-enterprise-shop-ps16 bash
fi

if [ "$1" = 'log' ];then
    docker logs -f hipay-enterprise-shop-ps16
fi


