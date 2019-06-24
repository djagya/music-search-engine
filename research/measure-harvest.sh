#!/usr/bin/env bash
# Run harvesters for both indexes on 1m documents using various batch sizes.

SIZES=(100 200 400 800 1600 3200 5000 7000 10000 13000 17000 20000)
LIMIT=1000000


echo "Harvesting spins"
echo "--------"

for size in "${SIZES[@]}"
do docker-compose run app php server/harvest.php spins 1 ${LIMIT} ${size}
done

echo "Harvesting EPF"
echo "--------"

for size in "${SIZES[@]}"
do docker-compose run app php server/harvest.php epf 1 ${LIMIT} ${size}
done
