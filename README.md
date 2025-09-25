# ModulFlow

**ModulFlow** is a dynamic admin panel built on **Laravel 12** and **Filament 3**.  
It empowers administrators and power-users to visually build, manage, and interact with custom data modules directly from a web interface, without writing a single line of code.

Think of it as a **no-code/low-code tool** for creating bespoke CRUD applications, complete with relational data, role-based access control, and dynamic dashboards.

---

## ✨ Key Features

| Feature                        | Description                                                                                                                               |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| 🚀 **Dynamic Module Builder**  | Create new data modules (e.g., "Products", "Clients", "Reports") directly from the UI.                                                    |
| 🔧 **Live Schema Management**  | Add, edit, and delete columns on the fly. Changes are instantly reflected in the database schema.                                         |
| 🔗 **Relational Fields**       | Create dropdown fields whose options are dynamically populated from other modules, establishing powerful database relationships visually. |
| 🛡️ **Advanced RBAC**           | Multi-tiered Role-Based Access Control (Super Admin, Admin, Manager, Staff) with granular, per-module permissions.                        |
| 📊 **Dynamic Dashboards**      | A unique dashboard for each role, showing relevant stats, recent activity, and quick actions.                                             |
| 🔎 **Sophisticated Filtering** | Automatically generated, advanced filters for tables based on column data types (date ranges, select options, booleans).                  |
| 📄 **Data Export**             | Export selected data to Excel, PDF, and Word formats with context-aware columns.                                                          |
| ☁️ **Cloudinary Integration**  | Seamlessly upload and manage images and files directly to Cloudinary, keeping the application stateless and serverless-ready.             |
| 📝 **Rich Content Editors**    | Includes WYSIWYG and long-text fields for managing complex content.                                                                       |
| 📜 **Full Audit Trail**        | Logs every significant action (create, update, delete) across all modules for complete accountability.                                    |
| 🎨 **Customizable Theme**      | Switch themes effortlessly right within the app.                                                          |

---

## 🛠️ Tech Stack

- **Backend**: Laravel 12
- **Admin Panel**: Filament 3
- **Permissions**: Spatie Laravel Permission & Filament Shield
- **Auditing**: Owen It Laravel Auditing
- **File Storage**: Cloudinary (via `cloudinary-labs/cloudinary-laravel`)
- **Exports**: `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `phpoffice/phpword`

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & NPM
- Relational database (sqlite, MySQL, PostgreSQL, etc)
- A **Cloudinary** account

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/dunalism/mdlflw
    cd mdlflw
    ```

2. **Install dependencies**

    ```bash
    composer install
    npm install
    npm run build
    ```

3. **Setup Environment, Migrations & Seeders**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    Update `.env` file:

    ```env
    CLOUDINARY_URL=cloudinary://<your_api_key>:<your_api_secret>@<your_cloud_name>
    ```

    Generate all stuff

    ```env
    php artisan app:modulflow-generate
    ```

4. **Start the server**

    ```bash
    php artisan serve
    ```

    Access your application at:  
    👉 [http://localhost:8000](http://localhost:8000)

---

#### 🔗 [Checkout the demo](https://modulflow.vercel.app/)

- **Super Admin**: `superadmin@modulflow.com`
- **Admin**: `admin@modulflow.com`
- **Manager**: `manager@modulflow.com`
- **Staff**: `staff@modulflow.com`

Password for all demo accounts: `password`
