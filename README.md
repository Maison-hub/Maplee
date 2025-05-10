# 🍁 Maplee

**Maplee** is a lightweight PHP router inspired by [Nitro](https://nitro.unjs.io/) and Next.js. It allows you to build routes from your folder structure with support for dynamic parameters, HTTP methods, and simple handlers.

> **Fun but serious.** Maplee is designed to be minimalist, flexible, and developer-friendly.

---

## ✨ Features

- ✅ File-based routing
- ✅ Dynamic parameters via `[param]` folders or files
- ✅ HTTP method handling via suffix (`.get.php`, `.post.php`, etc.)
- ✅ Simple handler function with `$context` (params, query, body)
- ✅ Customizable configuration
- ✅ Can be integrated into other frameworks (like Laravel)

---

# 🚀 Quick Start
## Routes structure
```
routes/
├── index.php               → /
├── blog/
│   ├── index.php           → /blog
│   └── post/
│       └── [id].get.php    → /blog/post/:id (GET)
```
## Example route handler

```php
use Maplee\RouteHandler;

RouteHandler::handle(function ($context) {
    return "Post ID is: " . $context->params['id'];
});
```

# Example usage:

## ⚙️ Configuration

Maplee uses a routes/ directory by default. You can override it with a maplee.config.php file at the root of your project:
```php
// maplee.config.php
return [
    'routesPath' => __DIR__ . '/custom-routes',
];
```
## 📂 Example project structure
```
project/
├── maplee.config.php
├── routes/
│   ├── index.php
│   └── blog/
│       └── [slug].get.php
├── public/
│   └── index.php   ← entry point (calls $router->handleRequest())
```
## 🛣️ Example route definition
'routes/blog/post/[id].get.php'
```php
<?php

use Maplee\MapleeRequest;

return function (MapleeRequest $request) {
    return "Post ID in POST Method ". $request->getParam('id');
};
```

# 🧭 Roadmap
- Caching routes (for performance)
- Typed responses (Response::json(), etc.)
- Middleware support
- CLI commands to scaffold routes
- JSON config file support

# 🤝 Contributing

Contributions are welcome! Please open an issue or submit a pull request for any bugs, features, or improvements.



