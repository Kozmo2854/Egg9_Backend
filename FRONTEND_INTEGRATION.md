# Frontend Integration Guide

This guide helps the React Native frontend team integrate with the Egg9 backend API.

## 🚀 Quick Integration (Copy & Paste)

**Everything you need to start:**

```typescript
// 1. Add to your config
const API_URL = 'http://localhost:8000/api';

// 2. Test it's working
const response = await fetch(`${API_URL}/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ 
    email: 'admin@egg9.com', 
    password: 'password123' 
  })
});
const { user, token } = await response.json();

// 3. Use the token for authenticated requests
const orders = await fetch(`${API_URL}/orders`, {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

**Test Accounts Ready:**
- Admin: `admin@egg9.com` / `password123`
- Customer: `user1@egg9.com` / `password123`

**Stock Available:**
- 1000 eggs at $5.99 per dozen (10 eggs)

---

## ✅ Backend Status

The backend is **fully operational** and running with Docker:
- **API URL**: `http://localhost:8000/api`
- **Database**: MySQL 8.0 (running in Docker)
- **All 21 endpoints**: Tested and working
- **Test Coverage**: 87 tests passing (100%)
- **Docker Status**: Check with `docker compose ps` in `/Egg9_Backend`

## Quick Start

### API Configuration

**Base URL:**
```typescript
// Development (Docker)
const API_URL = 'http://localhost:8000/api';

// Production (update when deployed)
const API_URL = 'https://api.egg9.com/api';
```

**Important**: The backend is running via Docker Compose. Make sure Docker containers are running:
```bash
# Check status
cd /path/to/Egg9_Backend
docker compose ps

# Start if not running
docker compose up -d
```

### Headers

All requests require these headers:
```typescript
const headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
};

// For authenticated requests, add:
headers['Authorization'] = `Bearer ${token}`;
```

## Authentication Flow

### 1. Register New User

```typescript
async function register(name: string, email: string, password: string) {
  const response = await fetch(`${API_URL}/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ name, email, password }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  const data = await response.json();
  // { user: {...}, token: "..." }
  
  // Store token
  await AsyncStorage.setItem('auth_token', data.token);
  await AsyncStorage.setItem('user', JSON.stringify(data.user));
  
  return data;
}
```

### 2. Login

```typescript
async function login(email: string, password: string) {
  const response = await fetch(`${API_URL}/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  const data = await response.json();
  
  // Store token and user
  await AsyncStorage.setItem('auth_token', data.token);
  await AsyncStorage.setItem('user', JSON.stringify(data.user));
  
  return data;
}
```

### 3. Get Current User

```typescript
async function getCurrentUser() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/user`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    if (response.status === 401) {
      // Token expired or invalid - redirect to login
      await AsyncStorage.removeItem('auth_token');
      throw new Error('Unauthorized');
    }
    throw new Error('Failed to fetch user');
  }

  return await response.json();
}
```

### 4. Logout

```typescript
async function logout() {
  const token = await AsyncStorage.getItem('auth_token');
  
  await fetch(`${API_URL}/logout`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  // Clear local storage
  await AsyncStorage.removeItem('auth_token');
  await AsyncStorage.removeItem('user');
}
```

## Order Management

### Get All Orders

```typescript
async function getOrders() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/orders`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.orders; // Array of orders
}
```

### Create Order

```typescript
async function createOrder(quantity: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/orders`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ quantity }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  const data = await response.json();
  return data.order;
}
```

### Update Order

```typescript
async function updateOrder(orderId: number, quantity: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/orders/${orderId}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ quantity }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  return await response.json();
}
```

### Cancel Order

```typescript
async function cancelOrder(orderId: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/orders/${orderId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  return await response.json();
}
```

## Weekly Stock

### Get Current Week Stock

```typescript
async function getWeeklyStock() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/weekly-stock`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.weeklyStock;
}
```

### Get Available Eggs

```typescript
async function getAvailableEggs() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/available-eggs`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.availableEggs; // number
}
```

## Subscriptions

### Get Active Subscription

```typescript
async function getActiveSubscription() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/subscriptions/current`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.subscription; // null if no active subscription
}
```

### Create Subscription

```typescript
async function createSubscription(quantity: number, period: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/subscriptions`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ quantity, period }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  const data = await response.json();
  return data.subscription;
}
```

### Cancel Subscription

```typescript
async function cancelSubscription(subscriptionId: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/subscriptions/${subscriptionId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  return await response.json();
}
```

## Admin Endpoints

Only accessible with admin role (`admin@egg9.com`).

### Get All Orders

```typescript
async function getAllOrders() {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/admin/orders`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.orders; // Includes userName and userEmail
}
```

### Update Weekly Stock

```typescript
async function updateWeeklyStock(availableEggs: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/admin/weekly-stock`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ availableEggs }),
  });

  return await response.json();
}
```

### Approve Order

```typescript
async function approveOrder(orderId: number) {
  const token = await AsyncStorage.getItem('auth_token');
  
  const response = await fetch(`${API_URL}/admin/orders/${orderId}/approve`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  return await response.json();
}
```

## Error Handling

### Standard Error Response

```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error details"]
  }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created (register, create order/subscription)
- `400` - Bad Request (business logic error)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (not admin)
- `404` - Not Found
- `422` - Validation Error

### Error Handling Example

```typescript
try {
  const order = await createOrder(20);
} catch (error) {
  if (error.message === 'Unauthorized') {
    // Redirect to login
    navigation.navigate('Login');
  } else if (error.message.includes('Insufficient stock')) {
    // Show stock error
    Alert.alert('Error', 'Not enough eggs available');
  } else {
    // Generic error
    Alert.alert('Error', error.message);
  }
}
```

## Data Types

### User Object

```typescript
interface User {
  id: number;
  name: string;
  email: string;
  role: 'customer' | 'admin';
  createdAt: string; // ISO 8601
}
```

### Order Object

```typescript
interface Order {
  id: number;
  userId: number;
  quantity: number;
  pricePerDozen: number;
  total: number;
  status: 'pending' | 'approved' | 'declined' | 'completed';
  deliveryStatus: 'not_delivered' | 'delivered';
  weekStart: string; // ISO 8601
  createdAt: string;
  updatedAt: string;
}
```

### Subscription Object

```typescript
interface Subscription {
  id: number;
  userId: number;
  quantity: number;
  frequency: 'weekly';
  period: number; // 4-12
  weeksRemaining: number;
  status: 'active' | 'paused' | 'cancelled' | 'completed';
  nextDelivery: string | null; // ISO 8601
  createdAt: string;
  updatedAt: string;
}
```

### WeeklyStock Object

```typescript
interface WeeklyStock {
  id: number;
  weekStart: string; // ISO 8601
  weekEnd: string;
  availableEggs: number;
  pricePerDozen: number;
  isOrderingOpen: boolean;
  deliveryDate: string | null;
  deliveryTime: string | null;
  allOrdersDelivered: boolean;
}
```

## Testing Credentials

✅ **These accounts are already created and ready to use:**

| Email | Password | Role | Name |
|-------|----------|------|------|
| admin@egg9.com | password123 | Admin | Admin User |
| user1@egg9.com | password123 | Customer | John Smith |
| user2@egg9.com | password123 | Customer | Jane Doe |

**Current Week Stock Available:**
- 1000 eggs available
- Price: $5.99 per dozen (10 eggs)
- Delivery: Saturday, 10:00 AM - 2:00 PM

## Important Notes & Business Rules

1. **🥚 CRITICAL: 1 Dozen = 10 Eggs** (not 12!)
   - This is the core business rule for Egg9
   - All calculations are based on 10 eggs per dozen
   
2. **Quantity Rules:**
   - All quantities must be **multiples of 10**
   - Minimum order: **10 eggs**
   - Subscription max: **30 eggs/week**
   - Subscription period: **4-12 weeks**

3. **Stock Management:**
   - Available stock is shared across all users
   - Stock calculation includes:
     - All pending orders (deducted)
     - All active subscriptions (deducted)
     - Your own order (added back, so you can edit)

4. **Orders:**
   - Only **one order per user per week**
   - Only **pending orders** can be updated/cancelled
   - Orders start as "pending" status

5. **Subscriptions:**
   - Only **one active subscription** per user
   - Creating a new subscription cancels the old one
   - Subscriptions auto-create orders every Monday at 00:01

6. **API Format:**
   - All dates in **ISO 8601** format
   - All prices as **float** (e.g., 5.99)
   - Token required for all protected endpoints

## Complete API Service Example

```typescript
// services/api.ts
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_URL = 'http://localhost:8000/api';

class ApiService {
  async getToken() {
    return await AsyncStorage.getItem('auth_token');
  }

  async request(endpoint: string, options: RequestInit = {}) {
    const token = await this.getToken();
    
    const headers: HeadersInit = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${API_URL}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Request failed');
    }

    return await response.json();
  }

  // Auth
  async login(email: string, password: string) {
    const data = await this.request('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    await AsyncStorage.setItem('auth_token', data.token);
    return data;
  }

  async register(name: string, email: string, password: string) {
    const data = await this.request('/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password }),
    });
    await AsyncStorage.setItem('auth_token', data.token);
    return data;
  }

  // Orders
  async getOrders() {
    return await this.request('/orders');
  }

  async createOrder(quantity: number) {
    return await this.request('/orders', {
      method: 'POST',
      body: JSON.stringify({ quantity }),
    });
  }

  // ... other methods
}

export default new ApiService();
```

## Usage in Components

```typescript
import ApiService from './services/api';

function OrderScreen() {
  const [orders, setOrders] = useState([]);
  
  useEffect(() => {
    loadOrders();
  }, []);

  async function loadOrders() {
    try {
      const data = await ApiService.getOrders();
      setOrders(data.orders);
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  }

  async function handleCreateOrder() {
    try {
      await ApiService.createOrder(20);
      loadOrders(); // Refresh
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  }

  return (
    // UI components
  );
}
```

---

## Quick API Test

Test the backend is working:

```bash
# 1. Check backend is running
curl http://localhost:8000/up

# 2. Test login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@egg9.com","password":"password123"}'

# Should return:
# {
#   "user": { ... },
#   "token": "1|..."
# }
```

## Troubleshooting

### Backend Not Responding

```bash
# Check if Docker containers are running
cd /path/to/Egg9_Backend
docker compose ps

# If not running, start them
docker compose up -d

# Check logs if issues
docker compose logs -f
```

### CORS Issues

The backend is configured to accept requests from:
- `http://localhost:8081` (React Native Metro)
- `http://localhost:19000` (Expo)
- `http://localhost:19006` (Expo)
- `exp://` URLs (Expo app)

If you need additional origins, they can be added in `config/cors.php`.

### Token Expired / 401 Errors

```typescript
// Always check for 401 and redirect to login
if (response.status === 401) {
  await AsyncStorage.removeItem('auth_token');
  navigation.navigate('Login');
}
```

## API Endpoint Summary

All 21 endpoints are implemented and tested:

**Public:**
- `POST /api/register` - Register new customer
- `POST /api/login` - Login and get token

**Protected (Customer):**
- `GET /api/user` - Get current user
- `POST /api/logout` - Logout
- `GET /api/weekly-stock` - Get current week info
- `GET /api/available-eggs` - Get available eggs for user
- `GET /api/orders` - Get all user orders
- `POST /api/orders` - Create order
- `GET /api/orders/current-week` - Get current week order
- `PUT /api/orders/{id}` - Update order
- `DELETE /api/orders/{id}` - Cancel order
- `GET /api/subscriptions/current` - Get active subscription
- `POST /api/subscriptions` - Create subscription
- `DELETE /api/subscriptions/{id}` - Cancel subscription

**Protected (Admin Only):**
- `GET /api/admin/orders` - All orders with user info
- `GET /api/admin/subscriptions` - All subscriptions
- `PUT /api/admin/weekly-stock` - Update available eggs
- `PUT /api/admin/delivery-info` - Update delivery schedule
- `POST /api/admin/orders/mark-delivered` - Mark all as delivered
- `PUT /api/admin/orders/{id}/approve` - Approve order
- `PUT /api/admin/orders/{id}/decline` - Decline order

## Example Response Format

All successful responses return JSON:

```json
{
  "order": {
    "id": 1,
    "userId": 1,
    "quantity": 20,
    "pricePerDozen": 5.99,
    "total": 11.98,
    "status": "pending",
    "deliveryStatus": "not_delivered",
    "weekStart": "2025-11-17T00:00:00.000000Z",
    "createdAt": "2025-11-19T14:55:41.000000Z",
    "updatedAt": "2025-11-19T14:55:41.000000Z"
  }
}
```

Error responses:

```json
{
  "message": "Insufficient stock available",
  "availableEggs": 50
}
```

## Support & Documentation

- **README.md** - Backend setup and Docker instructions
- **TESTING.md** - Testing documentation (87 tests passing)
- **DOCKER_SETUP.md** - Docker-specific setup guide
- **Egg9_API.postman_collection.json** - Complete API collection for testing
- **API_TEST_RESULTS.md** - Test results and verification

## Contact

The backend is **100% complete**, fully tested, and ready for integration! 🚀

All 21 endpoints are working, 87 tests passing, database seeded with test data.

