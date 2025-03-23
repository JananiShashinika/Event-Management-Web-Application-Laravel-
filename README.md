# Event Management Web Application (Laravel)

This is a web-based event management system built using Laravel and Vue.js. It helps users manage events by handling tasks like event creation, user registration, and scheduling.

## *Features*
- User authentication (login/register)
- Event creation and management
- Dashboard for tracking events
- Database integration with MySQL
- Responsive design with Vue.js

## *Technologies Used*
- **Laravel** (PHP framework)
- **MySQL** (Database)
- **Bootstrap** (Styling)
- **Vite** (Build tool)

## *Setup Instructions*

#### Clone the repository:
```sh
git clone https://github.com/JananiShashinika/Event-Management-Web-Application-Laravel-.git
```
#### Navigate to the project directory and install dependencies:
```sh  
cd Event-Management-Web-Application-Laravel-
composer install
npm install
```
#### Configure the .env file with database details.
Run migrations and seed the database:
```sh  
php artisan migrate --seed
```
#### Start the development server:
```sh
php artisan serve
npm run dev
```
