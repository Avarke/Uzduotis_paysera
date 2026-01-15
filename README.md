---

# Smart Booking Scheduler

## Overview

Smart Booking Scheduler is a simple appointment scheduling system consisting of a Laravel REST API backend and a Vue.js single-page application frontend, backed by an SQLite database.

The system allows a service provider (coach/admin) to define recurring working-hour rules, and clients to book available time slots by selecting a date, choosing a service, and entering an email address.

---

## Tech Stack

### Backend

* Laravel (API-only)
* PHP 8+
* SQLite
* Carbon (date and time handling)

### Frontend

* Vue 3 (Vite)
* Native HTML date and time inputs
* Fetch API for HTTP requests
* No external UI libraries (MVP-focused)

### Database

* SQLite
* Simple relational schema
* No authentication tables (login not required)

---

## Database Structure

### work_rules

Defines recurring availability rules per weekday.

Fields:

* `day_of_week` (integer, 0 = Sunday … 6 = Saturday)
* `start_time` (HH:MM)
* `end_time` (HH:MM)
* `slot_minutes` (integer, configurable)
* No overlapping rules allowed per weekday

These rules define availability templates and apply uniformly to all weeks.

---

### services

List of services available for booking.

Fields:

* `name`
* `is_active`

Services are treated as time-less (no duration logic) per assignment scope.

---

### client_bookings

Stores actual client bookings.

Fields:

* `date` (YYYY-MM-DD)
* `start_time` (HH:MM:SS)
* `service_id`
* `client_email`

Constraints:

* Prevents double booking of the same date and start time

Bookings are independent records and are not deleted when work rules change.

---

## API Endpoints

### Client / Public Endpoints

#### Get available services

GET /api/services

Returns a list of active services.

---

#### Get available time slots for a specific date

GET /api/availability?date=YYYY-MM-DD

Returns available time slots that:

* match the weekday’s work rules
* align to the defined slot interval
* are not already booked
* are not in the past (for the current day)

---

#### Create a booking

POST /api/client-bookings

Example payload:

```json
{
  "date": "2026-01-13",
  "start_time": "18:00",
  "service_id": 1,
  "client_email": "user@example.com"
}
```

Validations:

* correct date and time formats
* valid email format
* service exists and is active
* selected time fits an existing rule and slot grid
* prevents double booking

---

### Admin / Coach Endpoints

#### List work rules

GET /api/work-rules

---

#### Create a work rule

POST /api/work-rules

Validations:

* valid weekday and time range
* `end_time` must be after `start_time`
* `slot_minutes` must be positive and fit within the time window
* no overlapping rules for the same weekday

---

#### Update a work rule

PUT /api/work-rules/{id}

---

#### Delete a work rule

DELETE /api/work-rules/{id}

Deleting a work rule does not remove existing bookings.

---

## Frontend Functionality

### Client View

* Date picker with past dates disabled
* Dynamic list of available time slots
* Service selection dropdown
* Email validation
* Booking submission
* Slot list refresh after successful booking
* Floating success and error messages (toast notifications)

---

### Admin View

* Weekly calendar-style schedule (Google Calendar–like layout)
* One representative week view (rules apply to all weeks)
* Visual rule blocks scaled by duration
* Add and delete working-hour rules
* Configurable slot duration
* Overlap prevention enforced by backend

---

## Design Notes

* Work rules define availability templates, not bookings.
* Bookings are immutable historical records.
* Services are time-less by design to match assignment scope.
* API requests enforce JSON responses (`Accept: application/json`).
* Validation is implemented on both frontend and backend.

---

## Running the Project

### Backend

```bash
cd smart-booking-backend
php artisan serve
```

### Frontend

```bash
cd smart-booking-frontend
npm install
npm run dev
```
