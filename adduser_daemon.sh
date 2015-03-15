#! /bin/bash
# -*- coding: utf-8 -*-

# exit 0

# Monitor if a process on a system is running. 
# Script is stored in etc/cron.d/ and runs once every minute.

# 0 If process is not found, restart it.
# 1 If process is found, all ok.
# * If process running 2 or more, kill the last.


logfile="/home/pawse/lua/log/adduser.cron.log"
errorfile="/home/pawse/lua/log/adduser.error.log"
toutfile="/home/pawse/lua/log/adduser.log"
case "$(pgrep -f adduser_daemon.lua | wc -w)" in

0)  echo "Restarting daemon:     $(date)" >> $logfile
    rm -rf /var/lock/sas.sock
    nohup /home/pawse/lua/adduser/lua/adduser_daemon.lua >> $toutfile 2> $errorfile &
    ;;
1)  # all ok
    ;;
*)  echo "Removed double daemon: $(date)" >> $logfile
    kill $(pgrep -f adduser_daemon.lua | awk '{print $1}')
    ;;
esac
