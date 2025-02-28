#!/bin/sh

# comment-out mail.ini usage if mailstats not installed
[ -x "$(whereis -b mailstats | awk '{print $2}')" ] \
  || sed -i 's/^include.\+\<mail\>.\+$/; &/' "$BASEDIR/ini/monitask.ini"

# use correct devices in disk-linux.ini
[ -z "$(tail -1 "$BASEDIR/ini/disk-linux.ini")" ] \
  || echo "" >>"$BASEDIR/ini/disk-linux.ini"

DEV=$(df | awk '/^\/dev\// {split($1, a, /\//); print a[3]}')
DEV_JOIN=
METRIC_D_STATS=
METRIC_D_DF=
for dev_name in $DEV; do
  [ -z "$DEV_JOIN" ] \
    && DEV_JOIN=$dev_name \
    || DEV_JOIN="$DEV_JOIN|$dev_name"

  _MDS=$(grep '^D_stats_sda1' "$BASEDIR/ini/disk-linux.ini" | sed "s/sda1/$dev_name/g; s/^.*$/&\\\/")
  [ -z "$METRIC_D_STATS" ] \
    && METRIC_D_STATS=$_MDS \
    || METRIC_D_STATS="$METRIC_D_STATS
$_MDS"

  _MDD=$(grep '^D_df_sda1' "$BASEDIR/ini/disk-linux.ini" | sed "s/sda1/$dev_name/g; s/^.*$/&\\\/")
  [ -z "$METRIC_D_DF" ] \
    && METRIC_D_DF=$_MDD \
    || METRIC_D_DF="$METRIC_D_DF
$_MDD"
done

sed -i "/^disk.stats\>/{n;s/\/sda1\s*\//\$3 ~ \/$DEV_JOIN\//}" "$BASEDIR/ini/disk-linux.ini"

sed -i "/^D_stats_sda1/,/^$/c\\
$METRIC_D_STATS

" "$BASEDIR/ini/disk-linux.ini"

sed -i "/^D_df_sda1/,/^$/c\\
$METRIC_D_DF

" "$BASEDIR/ini/disk-linux.ini"

# comment-out network_cnx block if netstat not installed
[ -x "$(whereis -b netstat | awk '{print $2}')" ] \
  || sed -i -e '/^net\.stats/,/^$/ s/^.\+/; &/' -e '/^\[network_cnx]/,/^$/ s/^.\+/; &/' "$BASEDIR/ini/network-linux.ini"

[ -z "$(tail -1 "$BASEDIR/ini/network-linux.ini")" ] \
  || echo "" >>"$BASEDIR/ini/network-linux.ini"

CARDS=$(cat /proc/net/dev | awk '$1 ~ /...:/ {sub(/:/, "", $1); print $1}')
METRIC_N_BYTES=
METRIC_N_ERRS=
for card_name in $CARDS; do
  _MNB=$(grep '^N_[io]b_ens3' "$BASEDIR/ini/network-linux.ini" | sed "s/ens3/$card_name/g; s/^.*$/&\\\/")
  [ -z "$METRIC_N_BYTES" ] \
    && METRIC_N_BYTES=$_MNB \
    || METRIC_N_BYTES="$METRIC_N_BYTES
$_MNB"

  _MNE=$(grep '^N_[ioc][el]_ens3' "$BASEDIR/ini/network-linux.ini" | sed "s/ens3/$card_name/g; s/^.*$/&\\\/")
  [ -z "$METRIC_N_ERRS" ] \
    && METRIC_N_ERRS=$_MNE \
    || METRIC_N_ERRS="$METRIC_N_ERRS
$_MNE"
done

sed -i "/^N_[io]b_ens3/,/^$/c\\
$METRIC_N_BYTES

" "$BASEDIR/ini/network-linux.ini"

sed -i "/^N_[ioc][el]_ens3/,/^$/c\\
$METRIC_N_ERRS

" "$BASEDIR/ini/network-linux.ini"

# set linux environment
sudo mkdir /var/lib/monitask
sudo mkdir /var/www/monitask
sudo "$BASEDIR/run-monitask.php" --datastore
sudo sh -c "'$BASEDIR'/run-monitask.php --template > /var/www/monitask/index.html"
