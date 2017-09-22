#!/usr/bin/env bash

version=1.0.0

function cleanAndPackage()
{
    cp -R src/hipay_tpp hipay_tpp


    ############################################
    #####          CLEAN CONFIG             ####
    ############################################
    if [ -f hipay_tpp/config_fr.xml ]; then
        rm hipay_tpp/config_fr.xml
    fi

    ############################################
    #####          CLEAN IDEA FILE           ####
    ############################################
    if [ -d hipay_tpp/nbproject ]; then
        rm -R hipay_tpp/nbproject
    fi

    if [ -d hipay_tpp/.idea ]; then
        rm -R hipay_tpp/.idea
    fi

    find hipay_tpp/ -type d -exec cp index.php {} \;
    zip -r package-ready-for-prestashop/hipay_tpp-$version.zip hipay_tpp
    rm -R hipay_tpp
}

function show_help()
{
	cat << EOF
Usage: $me [options]

options:
    -h, --help                        Show this help
    -v, --version                     Configure version for package
EOF
}

function parse_args()
{
	while [[ $# -gt 0 ]]; do
		opt="$1"
		shift

		case "$opt" in
			-h|\?|--help)
				show_help
				exit 0
				;;
				esac
		case "$opt" in
			-v|--version)
              	version="$1"
				shift
				;;
		    esac
	done;
}

function main()
{
	parse_args "$@"
	cleanAndPackage
}

main "$@"

