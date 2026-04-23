#!/bin/bash
# Automated Batch Availability Checker
# Runs the PHP checker in multiple batches with safe delays
#
# Usage: ./run_availability_check.sh [total_products] [batch_size] [delay_seconds]
# Example: ./run_availability_check.sh 100 20 4
#   - Checks 100 products total
#   - In batches of 20 (5 runs)
#   - With 4 second delay between each product check

TOTAL_PRODUCTS=${1:-100}
BATCH_SIZE=${2:-20}
DELAY=${3:-4}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="${SCRIPT_DIR}/check_availability.php"

echo "=== Automated Availability Check ==="
echo "Total products to check: ${TOTAL_PRODUCTS}"
echo "Batch size: ${BATCH_SIZE}"
echo "Delay between requests: ${DELAY} seconds"
echo ""

# Calculate number of batches needed
BATCHES=$(( (TOTAL_PRODUCTS + BATCH_SIZE - 1) / BATCH_SIZE ))

echo "This will run ${BATCHES} batch(es)"
echo "Estimated time: $(( TOTAL_PRODUCTS * DELAY / 60 )) minutes"
echo ""

# Confirm before starting
read -p "Start checking? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

# Run batches
for ((i=1; i<=BATCHES; i++)); do
    echo ""
    echo "========================================="
    echo "Running batch ${i}/${BATCHES}"
    echo "========================================="
    
    php "${PHP_SCRIPT}" "${BATCH_SIZE}" "${DELAY}"
    
    if [ $i -lt $BATCHES ]; then
        echo ""
        echo "Waiting 30 seconds before next batch..."
        sleep 30
    fi
done

echo ""
echo "=== All batches completed ==="
