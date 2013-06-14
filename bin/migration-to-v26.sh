#!/bin/bash

# preconditions
[ -z "$1" ] && echo "You forgot to specify your DB name as the first parameter" && exit 1;
[ -z "$2" ] && echo "You forgot to specify your user for DB access as the second parameter" && exit 2;
[ ! -d "data/sql" ] && echo "cd to your project's root directory please" && exit 3;
[ ! -z "$3" ] && echo "You'll do a restore-only procedure"

DB=$1
USER=$2

echo "Are you sure you want to continue with those parameters :"
echo Database: $DB
echo User: $USER
[ ! -z "$3" ] && echo "With a restore only procedure (no DB backup, no previous backup overwritting)"
[ -z "$3" ] && echo "With a backup which is going to be written in data/sql/$DB-`date +%Y%m%d`.pgdump"
echo ""
echo "To continue press ENTER"
echo "To cancel press CTRL+C NOW !!"
read

# preliminary modifications & backup
[ -z "$3" ] && pg_dump -Fc $DB > data/sql/$DB-`date +%Y%m%d`.pgdump && echo "DB dumped"

# recreation and data backup
dropdb $DB && createdb $DB && \
echo "GRANT ALL ON DATABASE $DB TO $USER" | psql $DB && \
./symfony doctrine:build  --all --no-confirmation && \
cat data/sql/$DB-`date +%Y%m%d`.pgdump | pg_restore --disable-triggers -Fc -a -d $DB
cat config/doctrine/functions-pgsql.sql | psql $DB && \
./symfony cc

# final data modifications


# final informations
echo ""
echo ""
echo "Don't forget to configure those extra features :"
echo "e-venement messaging system: http://[YOUR E-VENEMENT BASE ROOT]/liJappixPlugin + config/project.yml + per-users settings"

echo ""
echo "Don't forget to inform your users about those evolutions"
