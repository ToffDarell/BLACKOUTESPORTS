# Admin Reporting System

This document explains how to use the new Admin Reporting System added to the Blackout Esports management platform.

## Overview

The reporting system allows administrators to generate and print various reports from the admin dashboard. Reports are generated as styled PDFs using the mPDF library.

## Installation Requirements

Before using the reporting feature, ensure that you have the following requirements installed:

1. PHP 7.4 or higher
2. Composer (for package management)
3. mPDF library

To install the required mPDF library, run the following command in the project root directory:

```bash
composer install
```

## Available Reports

The system supports generating the following reports:

1. **Reservation Summary Report**
   - Lists all reservations with user details, computers, dates, times, and status
   - Includes QR code paths if generated

2. **Transaction Report**
   - Lists all payment transactions with user information, amounts paid, and reference numbers
   - Includes thumbnails of payment proof images if available

3. **User List**
   - Complete list of registered users with membership status
   - Includes contact information and registration dates

4. **Refund Requests Report**
   - Lists all refund requests with status and details
   - Includes GCash reference numbers and refund dates

5. **Tournament Registrations**
   - Lists all tournament registrations with team and captain information
   - Shows payment status and proof of payment 

6. **Advisory Logs**
   - Lists all system advisories that have been posted
   - Includes creation date/time and status

7. **Scheduled Advisories**
   - Shows upcoming system advisories
   - Includes scheduled start date/time

## How to Use

1. Log in to the Admin Dashboard
2. Click on the "Print Report" button in the Quick Actions section
3. In the modal that appears:
   - Select the report type you wish to generate
   - Choose a date range filter (Today, This Week, This Month, or Custom)
   - If you selected Custom, enter the start and end dates
   - Select any additional filters specific to the report type
4. Click "Generate Report" to create and view the PDF
5. The report will open in a new browser tab
6. You can download or print the PDF using your browser's built-in functions

## Filtering Options

Each report type has specific filters available:

- **Date Range**: Filter data by Today, This Week, This Month, or a Custom date range
- **Reservation Status**: Filter reservations by Confirmed, Pending, or Cancelled status
- **User Status**: Filter users by Member or Non-Member status
- **Refund Status**: Filter refund requests by Approved, Declined, or Refunded status
- **Tournament Payment Status**: Filter tournament registrations by Paid or Unpaid status
- **Advisory Status**: Filter advisories by Active or Inactive status

## Troubleshooting

If you encounter issues generating reports:

1. Ensure that the mPDF library is properly installed
2. Check that the server has appropriate permissions to write temporary files
3. Verify that the database connection is working correctly
4. Make sure all required tables and fields exist in the database

For more technical information, review the code in `generate_report.php`. 