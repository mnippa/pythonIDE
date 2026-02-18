#!/bin/bash
# Test the API response with curl
echo "=== Testing API Response ==="

# Create a temporary cookie jar for the session
COOKIES="/tmp/cookies.txt"
rm -f "$COOKIES"

# Login first
echo -n "Logging in as admin... "
curl -s -c "$COOKIES" \
  -X POST \
  -H "Content-Type: application/x-www-form-urlencoded" \
  "http://localhost/pythonIDE/api/auth/login.php" \
  --data "email=admin@test.de&password=testpass123" > /dev/null
echo "Done"

# Then call the API with test_mode
echo -e "\nCalling API with assignment_id=12&test_mode=1..."
curl -s -b "$COOKIES" \
  "http://localhost/pythonIDE/api/tasks/list.php?assignment_id=12&test_mode=1" | \
  python3 -m json.tool | grep -A 5 -B 2 "solution_code" | head -50

echo -e "\n✓ Check if solution_code appears in the response above"
