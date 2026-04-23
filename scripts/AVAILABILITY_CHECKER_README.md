# Product Availability Checker (No API)

This system checks Amazon product availability in batches without using the official API.

## ⚠️ Important Warnings

- **Rate Limiting**: Amazon actively blocks automated requests. Always use delays of 3-5 seconds minimum between requests.
- **IP Blocking**: Running too many checks too fast can temporarily block your server's IP.
- **HTML Changes**: Amazon frequently changes their page structure, so detection patterns may need updates.
- **Terms of Service**: Review Amazon Associates TOS before automating any checks.

## Files Created

1. **`scripts/check_availability.php`** - Main PHP script that checks products in batches
2. **`scripts/run_availability_check.sh`** - Bash wrapper for running multiple batches

## Usage

### Quick Test (Small Batch)
```bash
cd /workspace/scripts
php check_availability.php 5 5
```
This checks 5 products with 5-second delays between requests.

### Medium Batch
```bash
php check_availability.php 20 4
```
Checks 20 products with 4-second delays (~80 seconds total).

### Large Batch with Wrapper Script
```bash
./run_availability_check.sh 100 20 4
```
This will:
- Check 100 products total
- In 5 batches of 20 products each
- With 4-second delays between requests
- 30-second pause between batches
- **Estimated time: ~7-8 minutes**

## Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| batch_size | 20 | Products to check per run (max 50) |
| delay_seconds | 3 | Seconds to wait between requests |

## How It Works

1. **Fetches products** from database ordered by oldest `last_synced_at` first
2. **Extracts Amazon URLs** from affiliate links or constructs from ASIN
3. **Fetches product pages** using cURL with realistic browser headers
4. **Parses HTML** for availability indicators:
   - "Currently unavailable"
   - "Out of Stock"
   - "Temporarily out of stock"
   - Missing "Add to Cart" buttons
5. **Updates database** with:
   - `status`: 'published' or 'out_of_stock'
   - `price_amount`: extracted price if available
   - `last_synced_at`: timestamp of check

## Database Status Values

- `published` - Product is available
- `out_of_stock` - Product currently unavailable
- `discontinued` - Manually marked as permanently unavailable

## Scheduling with Cron

To run automatically every night at 2 AM:

```bash
crontab -e
```

Add this line:
```
0 2 * * * cd /workspace/scripts && php check_availability.php 50 4 >> /var/log/availability_check.log 2>&1
```

This checks 50 products nightly with 4-second delays.

## Best Practices

1. **Start Small**: Test with 5-10 products first
2. **Use Delays**: Never go below 3 seconds between requests
3. **Run Off-Peak**: Schedule during low-traffic hours
4. **Monitor Logs**: Watch for errors or blocking
5. **Prioritize**: Focus on high-traffic products first
6. **Don't Over-Check**: Once per week is usually sufficient

## Troubleshooting

### Too Many Errors / Timeouts
- Increase delay between requests
- Reduce batch size
- Check server network connectivity

### Getting Blocked
- Wait a few hours before retrying
- Reduce frequency of checks
- Consider using proxy rotation for large catalogs

### False Positives (Marked Unavailable When Available)
- Amazon may have changed HTML structure
- Check the actual product page manually
- Update the regex patterns in `checkAvailability()` function

## Manual Workflow Alternative

If automation is too risky for your situation:

1. Export product list to CSV
2. Manually check top products weekly
3. Update status in admin panel
4. Add disclaimer: "Prices and availability subject to change"

## Time Calculations

| Products | Delay | Total Time |
|----------|-------|------------|
| 20 | 3s | ~1 minute |
| 50 | 3s | ~2.5 minutes |
| 100 | 4s | ~6.5 minutes |
| 200 | 5s | ~17 minutes |
| 500 | 5s | ~42 minutes |

**Recommendation**: For catalogs over 200 products, split across multiple days or run continuously overnight.
