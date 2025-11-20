#!/bin/bash

# Egg9 API Testing Script
# This script tests all 21 API endpoints

API_URL="http://localhost:8000/api"
TOKEN=""

echo "================================"
echo "Egg9 API Endpoint Testing"
echo "================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local auth=$4
    local description=$5
    
    echo "Testing: $description"
    echo "  $method $endpoint"
    
    if [ -n "$auth" ]; then
        headers="-H 'Authorization: Bearer $TOKEN'"
    else
        headers=""
    fi
    
    if [ -n "$data" ]; then
        eval curl -s -X $method "$API_URL$endpoint" \
            -H "'Content-Type: application/json'" \
            -H "'Accept: application/json'" \
            $headers \
            -d "'$data'" -w "\n  Status: %{http_code}\n"
    else
        eval curl -s -X $method "$API_URL$endpoint" \
            -H "'Accept: application/json'" \
            $headers \
            -w "\n  Status: %{http_code}\n"
    fi
    
    echo ""
}

echo "========================================
"
echo "1. AUTHENTICATION ENDPOINTS (4)"
echo "========================================"

# Register
test_endpoint "POST" "/register" \
    '{"name":"Test User","email":"test@example.com","password":"password123"}' \
    "" \
    "Register new user"

# Login
echo "Logging in as admin..."
RESPONSE=$(curl -s -X POST "$API_URL/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"email":"admin@egg9.com","password":"password123"}')

TOKEN=$(echo $RESPONSE | grep -o '"token":"[^"]*' | sed 's/"token":"//')

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ Login successful${NC}"
    echo "  Token: ${TOKEN:0:50}..."
else
    echo -e "${RED}✗ Login failed (database not initialized)${NC}"
    echo "  Run: php artisan migrate:fresh --seed"
fi
echo ""

# Get current user
test_endpoint "GET" "/user" "" "auth" "Get current user"

# Logout
test_endpoint "POST" "/logout" "" "auth" "Logout"

echo "========================================"
echo "2. WEEKLY STOCK ENDPOINTS (2)"
echo "========================================"

test_endpoint "GET" "/weekly-stock" "" "auth" "Get current week stock"
test_endpoint "GET" "/available-eggs" "" "auth" "Get available eggs"

echo "========================================"
echo "3. ORDER ENDPOINTS (5)"
echo "========================================"

test_endpoint "GET" "/orders" "" "auth" "Get all orders"
test_endpoint "GET" "/orders/current-week" "" "auth" "Get current week order"
test_endpoint "POST" "/orders" '{"quantity":20}' "auth" "Create order (20 eggs)"
test_endpoint "PUT" "/orders/1" '{"quantity":30}' "auth" "Update order to 30 eggs"
test_endpoint "DELETE" "/orders/1" "" "auth" "Cancel order"

echo "========================================"
echo "4. SUBSCRIPTION ENDPOINTS (3)"
echo "========================================"

test_endpoint "GET" "/subscriptions/current" "" "auth" "Get active subscription"
test_endpoint "POST" "/subscriptions" '{"quantity":20,"period":8}' "auth" "Create subscription (20 eggs, 8 weeks)"
test_endpoint "DELETE" "/subscriptions/1" "" "auth" "Cancel subscription"

echo "========================================"
echo "5. ADMIN ENDPOINTS (7)"
echo "========================================"

test_endpoint "GET" "/admin/orders" "" "auth" "Get all orders (admin)"
test_endpoint "GET" "/admin/subscriptions" "" "auth" "Get all subscriptions (admin)"
test_endpoint "PUT" "/admin/weekly-stock" '{"availableEggs":1500}' "auth" "Update weekly stock"
test_endpoint "PUT" "/admin/delivery-info" '{"deliveryDate":"2024-12-21","deliveryTime":"10:00 AM - 2:00 PM"}' "auth" "Update delivery info"
test_endpoint "POST" "/admin/orders/mark-delivered" "" "auth" "Mark all orders delivered"
test_endpoint "PUT" "/admin/orders/1/approve" "" "auth" "Approve order"
test_endpoint "PUT" "/admin/orders/1/decline" "" "auth" "Decline order"

echo "========================================"
echo "✓ All 21 endpoints tested!"
echo "========================================"
echo ""
echo "Summary:"
echo "  - Authentication: 4 endpoints"
echo "  - Weekly Stock: 2 endpoints"
echo "  - Orders: 5 endpoints"
echo "  - Subscriptions: 3 endpoints"
echo "  - Admin: 7 endpoints"
echo "  Total: 21 endpoints"
echo ""
echo "Note: If you see database errors, run:"
echo "  php artisan migrate:fresh --seed"

