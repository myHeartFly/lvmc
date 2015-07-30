#!/bin/bash
rm -rf lvmcexport
svn export https://192.168.1.200/svn/lvmc/trunk lvmcexport --username chenlei --password chenl@TC
cd lvmcexport
tar zcvf lvmc.tar.gz application/controllers application/models application/views application/libraries application/helpers
scp lvmc.tar.gz chenlei@testport.itouchchina.com:/tmp/chenlei
# scp lvmc.tar.gz yangqing@itouchchina.com:/tmp/
cd ..
rm -rf lvmcexport