# Rent-Management-System

## Overview

The Rent Management System is a professional multi-user property management platform designed to simplify communication, rent collection, tenant management, property monitoring, and financial tracking between landlords and tenants.

The system is built to support both small-scale and large-scale property management businesses. It can be deployed as a standalone application for a single landlord or as a SaaS (Software as a Service) platform where multiple landlords subscribe and manage their properties independently.

This platform automates manual rental processes such as rent collection, invoice generation, maintenance tracking, room allocation, reporting, and tenant communication.

---

# System Objectives

The main objectives of the system are:

* Simplify rental property management
* Reduce manual paperwork
* Improve communication between landlords and tenants
* Automate rent calculations and payment tracking
* Generate professional invoices and receipts
* Track maintenance requests efficiently
* Improve financial transparency
* Support multiple landlords in one platform
* Provide secure role-based access control

---

# Main Features

## Authentication and Authorization

* User registration
* Secure login/logout
* Password encryption
* Forgot password functionality
* Role-based authentication
* Session management
* Multi-user access control

---

## Tenant Management

* Add and manage tenants
* Store tenant personal information
* Assign tenants to rooms
* Track tenant move-in and move-out dates
* Tenant payment history
* Emergency contact management
* Tenant status tracking

---

## Property Management

* Register properties/buildings
* Add rooms and room details
* Manage room availability
* View occupied and vacant rooms
* Property categorization
* Room pricing management

---

## Rent Payment Management

* Record rent payments
* Automatic balance calculation
* Overdue rent tracking
* Payment history management
* Support multiple payment methods
* Payment verification
* Generate payment records

---

## Invoice and Receipt Management

* Automatic invoice generation
* Receipt generation
* PDF invoice support
* Printable receipts
* Due date tracking
* Payment confirmation

---

## Notification System

* SMS notifications
* Email notifications
* In-app notifications
* Rent due reminders
* Payment confirmations
* Maintenance updates

---

## Maintenance Request Management

* Submit maintenance requests
* Track repair status
* Assign repair tasks
* Repair completion tracking
* Communication between tenants and landlords

---

## Reports and Analytics

* Monthly income reports
* Yearly revenue reports
* Tenant debt reports
* Expense reports
* Occupancy reports
* Financial analytics
* Export reports to PDF/Excel

---

## Expense Management

* Track operational expenses
* Repair cost tracking
* Utility bill management
* Security and cleaning expenses
* Profit and loss calculation

---

## Rental Agreement Management

* Upload rental agreements
* Store contract documents
* Agreement expiry tracking
* Digital document support
* Security deposit tracking

---

## Dashboard System

### Admin Dashboard

* Total landlords
* Total tenants
* Subscription statistics
* Total platform revenue
* Active users
* System activities

### Landlord Dashboard

* Total properties
* Monthly income
* Outstanding balances
* Vacant rooms
* Tenant statistics

### Tenant Dashboard

* Rent balance
* Payment history
* Notifications
* Maintenance requests
* Due dates

---

## System Screenshots & Interface
*Below are visual highlights of the RentPay platform. (Screenshots represent current fully implemented modules)*

### Authentication & Access Control
> Secure login and user registration flows with dynamic role assignment.
![Login Page](../System_View/login.jpg)
![Registration page](../System_View/register.jpg)

### Core System Dashboard
> Comprehensive data visualization for active users, financial statistics, and system states.
![Home dashboard](../System_View/Home_Dashboard.jpg)

---

# User Roles

## 1. Admin

The Admin manages the entire software platform.

### Responsibilities

* Manage all users
* Approve or suspend landlords
* Manage subscriptions
* Monitor system activities
* Manage security
* View platform analytics
* Handle backups and recovery
* Provide technical support
* Manage permissions
* Monitor audit logs

---

## 2. Landlord

The Landlord manages properties and tenants.

### Responsibilities

* Add and manage properties
* Add and manage rooms
* Register tenants
* Record rent payments
* Generate invoices and receipts
* Manage maintenance requests
* View financial reports
* Track property performance

---

## 3. Tenant

The Tenant accesses personal rental information.

### Responsibilities

* View rent information
* View invoices and receipts
* Make payments
* Submit maintenance requests
* Receive notifications
* Track payment history

---

# System Workflow

## Step 1: Landlord Registration

The landlord creates an account and subscribes to the platform.

## Step 2: Property Registration

The landlord adds properties and room information.

## Step 3: Tenant Registration

Tenants are registered and assigned to rooms.

## Step 4: Rent Processing

The system generates rent records and invoices.

## Step 5: Payment Collection

Tenants make payments through supported payment methods.

## Step 6: Receipt Generation

The system automatically generates receipts.

## Step 7: Maintenance Tracking

Tenants submit repair requests and landlords manage repairs.

## Step 8: Reporting

The system generates reports and analytics automatically.

---

# Database Structure

## Users Table

| Attribute  | Description            |
| ---------- | ---------------------- |
| user_id    | Unique user identifier |
| full_name  | User full name         |
| email      | User email             |
| phone      | Phone number           |
| password   | Encrypted password     |
| role       | User role              |
| status     | Account status         |
| created_at | Account creation date  |

---

## Landlords Table

| Attribute   | Description         |
| ----------- | ------------------- |
| landlord_id | Landlord identifier |
| user_id     | Linked user account |
| national_id | National ID         |
| address     | Landlord address    |
| photo       | Profile image       |
| created_at  | Registration date   |

---

## Tenants Table

| Attribute         | Description         |
| ----------------- | ------------------- |
| tenant_id         | Tenant identifier   |
| user_id           | Linked user account |
| landlord_id       | Property owner      |
| national_id       | Tenant ID           |
| occupation        | Job/business        |
| emergency_contact | Emergency contact   |
| move_in_date      | Move-in date        |
| status            | Tenant status       |

---

## Properties Table

| Attribute     | Description         |
| ------------- | ------------------- |
| property_id   | Property identifier |
| landlord_id   | Property owner      |
| property_name | Building name       |
| location      | Property address    |
| total_rooms   | Number of rooms     |
| description   | Property details    |

---

## Rooms Table

| Attribute   | Description        |
| ----------- | ------------------ |
| room_id     | Room identifier    |
| property_id | Linked property    |
| room_number | Room number        |
| rent_amount | Rental price       |
| room_size   | Room size          |
| status      | Room status        |
| description | Additional details |

---

## Payments Table

| Attribute        | Description           |
| ---------------- | --------------------- |
| payment_id       | Payment identifier    |
| tenant_id        | Tenant reference      |
| room_id          | Room reference        |
| amount_paid      | Amount paid           |
| payment_date     | Date of payment       |
| payment_method   | Payment type          |
| transaction_code | Transaction reference |
| balance          | Remaining balance     |

---

## Invoices Table

| Attribute  | Description           |
| ---------- | --------------------- |
| invoice_id | Invoice identifier    |
| tenant_id  | Tenant reference      |
| room_id    | Room reference        |
| amount_due | Required payment      |
| due_date   | Payment deadline      |
| issue_date | Invoice creation date |
| status     | Invoice status        |

---

## Expenses Table

| Attribute    | Description        |
| ------------ | ------------------ |
| expense_id   | Expense identifier |
| property_id  | Property reference |
| expense_type | Expense category   |
| amount       | Expense amount     |
| expense_date | Expense date       |
| description  | Expense details    |

---

## Maintenance Requests Table

| Attribute    | Description        |
| ------------ | ------------------ |
| request_id   | Request identifier |
| tenant_id    | Tenant reference   |
| room_id      | Room reference     |
| title        | Problem title      |
| description  | Issue details      |
| request_date | Submission date    |
| status       | Repair status      |

---

## Notifications Table

| Attribute       | Description             |
| --------------- | ----------------------- |
| notification_id | Notification identifier |
| user_id         | Receiver                |
| title           | Notification title      |
| message         | Notification message    |
| type            | Notification type       |
| status          | Read/unread             |
| sent_at         | Date sent               |

---

# Database Relationships

* One landlord can own many properties
* One property can contain many rooms
* One room can be assigned to one tenant at a time
* One tenant can make many payments
* One payment generates one receipt
* One tenant can submit many maintenance requests

---

# Technology Stack

## Frontend Technologies

* HTML5
* CSS3
* JavaScript
---

## Backend Technologies
* PHP
---

## Database Systems

* MySQL
---

# Security Features

* Password hashing
* Role-based access control
* Session authentication
* Activity logging
* Input validation
* SQL injection prevention
* Secure APIs
* Data isolation for landlords

---

# SaaS Architecture

The system supports multi-landlord architecture.

Each landlord has independent:

* Properties
* Rooms
* Tenants
* Payments
* Reports

This ensures data privacy and scalability.

---

# Subscription Model

The platform can support subscription packages.

## Example Packages

| Package  | Features                                    |
| -------- | ------------------------------------------- |
| Basic    | Small property management                   |
| Standard | Medium property management                  |
| Premium  | Advanced analytics and unlimited properties |

---

# Advanced Features

* Mobile money integration
* Online payments
* QR code payments
* AI-based rent prediction
* Cloud backup
* Mobile application support
* Real-time notifications
* Multi-language support
* Audit logging
* Data analytics

---

# Future Improvements

Potential future upgrades include:

* AI analytics dashboard
* Face recognition authentication
* Fingerprint integration
* Blockchain payment verification
* Smart contract agreements
* IoT smart property integration

---

# System Benefits

## For Landlords

* Faster rent collection
* Better financial tracking
* Easier tenant management
* Reduced paperwork
* Better reporting

## For Tenants

* Easier payment tracking
* Quick maintenance reporting
* Transparent billing
* Better communication

## For Admin

* Centralized management
* Platform scalability
* Revenue monitoring
* User management

---

# Project Structure Example
rent-management-system/
│
├── modules/
│   ├── Authentication/
│   ├── Landlords/
│   ├── Tenants_Management/
│   ├── Property_Management/
│   ├── Rooms/
│   ├── Rent_Payment/
│   ├── Invoice_and_Report/
│   ├── maintenance/
│   ├── Expense_Management/
│   ├── Notifications/
│   ├── Reports/
│   ├── Dashboard/
│   └── subscriptions/
|   
│
├── shared/
│   ├── middleware/
│   ├── utilities/
│   ├── helpers/
│   ├── constants/
│   └── validators/
│
├── database/
│
├── uploads/
│
├── logs/
│
├── config/
│
├── tests/
│
└── README.md

# Installation Guide

### Prerequisites
* PHP (version 8.0 or higher recommended)
* MySQL or PostgreSQL Database
* Apache Web Server (XAMPP / WampServer) or Windows Subsystem for Linux (WSL) environment

## Installation Steps

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/mafurudaniel99-cyber/RentPay.git](https://github.com/mafurudaniel99-cyber/RentPay.git)

### 2. Configure Database

Create database and import SQL schema.

### 3. Configure Environment Variables

Set:

* Database credentials
* API keys
* Mail configuration
* SMS gateway configuration

### 4. Run Application

```bash
npm start
```

or

```bash
php artisan serve
```
---

# Conclusion

The Rent Management System provides a complete digital solution for property management by integrating tenant management, payment processing, maintenance handling, reporting, and communication into one centralized platform.

The system improves operational efficiency, financial transparency, and user experience for landlords, tenants, and administrators.

It is scalable, secure, and suitable for both small and enterprise-level property management businesses.

---

# Author

Developed By Daniel Japhet Mafuru.
[mafurudaniel99@gmail.com]
The professional property management and SaaS-based rental business operations.
