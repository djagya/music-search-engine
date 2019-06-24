#!/usr/bin/env bash
# run for batches 100, 200, 400, 800, 1600, ... for 1m rows

#SIZES=(100 200 400 800 1600 3200 5000 7000 10000 13000 17000 20000)
SIZES=(100 200)
LIMIT=10000


echo "Harvesting spins"
echo "--------"

for size in "${SIZES[@]}"
do docker-compose exec app php server/harvest.php spins 1 ${LIMIT} ${size}
done

echo "Harvesting EPF"
echo "--------"

for size in "${SIZES[@]}"
do docker-compose exec app php server/harvest.php epf 1 ${LIMIT} ${size}
done
