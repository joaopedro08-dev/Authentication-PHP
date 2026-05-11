# PHP Auth API

Pure PHP authentication API with no framework, manual JWT handling, refresh tokens stored in the database, and standardized JSON responses.

## Entry Point

The main entry point is [index.php](index.php). It loads `.env` variables, applies security headers, configures CORS, and dispatches the request to [routes/router.php](routes/router.php).

```php
php -S localhost:8000 index.php
```

The request flow is:

1. `.htaccess` rewrites all requests to `index.php`.
2. `index.php` loads the environment and global headers.
3. `routes/router.php` parses the HTTP method and path.
4. Handlers in `http/auth/*.php` call [controller/auth.controller.php](controller/auth.controller.php).
5. The controller returns responses in the standard JSON format.

## Response Format

All responses follow this structure:

```json
{
  "success": true,
  "data": {},
  "message": "Success message"
}
```

For errors:

```json
{
  "success": false,
  "data": null,
  "message": "Error description"
}
```

## Environment Variables

Configure `.env` with at least these values:

```env
APP_ENV=development
APP_URL=http://localhost
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=auth_php
DB_USER=root
DB_PASS=password
JWT_SECRET=a_long_and_secure_key
ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
```

## Database

### users

Expected fields:

- id
- name
- email
- password_hash
- created_at
- updated_at

### refresh_tokens

Expected fields:

- id
- user_id
- token
- expires_at
- created_at

## Endpoints

### POST /api/auth/register

Creates a new user.

JSON body:

```json
{
  "name": "John Silva",
  "email": "john@email.com",
  "password": "password12345"
}
```

### POST /api/auth/login

Authenticates the user and returns a JWT access token plus a refresh token.

JSON body:

```json
{
  "email": "john@email.com",
  "password": "password12345"
}
```

### POST /api/auth/refresh

Generates a new access token using the refresh token stored in the database.

JSON body:

```json
{
  "refresh_token": "token_received_on_login"
}
```

### POST /api/auth/logout

Deletes the refresh token from the database.

JSON body:

```json
{
  "refresh_token": "token_received_on_login"
}
```

### GET /api/auth/verify

Validates the JWT sent in the Authorization header.

Header:

```http
Authorization: Bearer ACCESS_TOKEN
```

### GET /api/auth/me

Returns the authenticated user's data.

Header:

```http
Authorization: Bearer ACCESS_TOKEN
```

### PUT /api/auth/profile

Updates the authenticated user's name and e-mail.

Header:

```http
Authorization: Bearer ACCESS_TOKEN
Content-Type: application/json
```

JSON body:

```json
{
  "name": "New Name",
  "email": "new@email.com"
}
```

### PUT /api/auth/password

Updates the authenticated user's password.

Header:

```http
Authorization: Bearer ACCESS_TOKEN
Content-Type: application/json
```

JSON body:

```json
{
  "current_password": "password12345",
  "new_password": "newPassword12345"
}
```

## Postman Testing

### 1. Create a collection

Create a collection called `PHP Auth API` and set an environment variable:

- `base_url` = `http://localhost`

If your project runs on a different local Apache port, update `base_url` accordingly.

### 2. Add requests

Add the following requests to the collection:

- `POST {{base_url}}/api/auth/register`
- `POST {{base_url}}/api/auth/login`
- `POST {{base_url}}/api/auth/refresh`
- `POST {{base_url}}/api/auth/logout`
- `GET {{base_url}}/api/auth/verify`
- `GET {{base_url}}/api/auth/me`
- `PUT {{base_url}}/api/auth/profile`
- `PUT {{base_url}}/api/auth/password`

### 3. Default headers

Use these headers for requests that send JSON:

```http
Content-Type: application/json
```

For protected routes, also add:

```http
Authorization: Bearer {{access_token}}
```

### 4. Useful Postman variables

After login, store the tokens in environment variables:

- `access_token`
- `refresh_token`

Example test script for the login request:

```javascript
const response = pm.response.json();
if (response.success && response.data) {
  pm.environment.set('access_token', response.data.access_token);
  pm.environment.set('refresh_token', response.data.refresh_token);
}
```

### 5. Suggested test order

1. `POST /api/auth/register`
2. `POST /api/auth/login`
3. `GET /api/auth/me`
4. `GET /api/auth/verify`
5. `POST /api/auth/refresh`
6. `PUT /api/auth/profile`
7. `PUT /api/auth/password`
8. `POST /api/auth/logout`

## Security Notes

- Passwords are stored with `password_hash(..., PASSWORD_BCRYPT)`.
- JWT is signed manually with HMAC-SHA256 using `JWT_SECRET`.
- Refresh token é armazenado no banco e deve ser salvo de forma segura no cliente.
- Há uma dica de rate limiting nos handlers para futura integração com Redis ou APCu.

## Verificação rápida

Se estiver usando Apache local, acesse:

- `GET /api/auth/me` com `Authorization: Bearer ...`
- `POST /api/auth/login`

Se a resposta vier em JSON com `success`, a API está funcionando.
