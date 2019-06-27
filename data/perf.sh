#!/usr/bin/env bash

# Prepares merged, sorted and cleaned performance log from Spinitron logs.

DIR=`dirname "$0"`/perf

cat ${DIR}/performance*.log | sort -k 1 | grep -e 'perf\\typing' | cut -d' ' -f 1,2,4- > ${DIR}/../perf.log
