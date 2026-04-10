# KPI_Management_System
============GROUP 7================
SYAHIRAH BINTI SHAMSUDIN 0137475
NUR INSYIRAH BINTI ANIS 0137471
HASHIKA KESHREEN AMARASIRI 0138472

INSTALLATION GUIDE – KPI MANAGEMENT SYSTEM

1. Extract Project Files
  Download the ZIP file and extract it.
  You should get:
 - KPI_Management_System (project folder)
 - kpi_system.sql (database file)

2. Move Project to XAMPP
 - Copy the KPI_Management_System folder and paste it into: C:\xampp\htdocs\

3. Start XAMPP Services
 Open XAMPP Control Panel and start:
 - Apache
 - MySQL

4. Create Database
  Open your browser and go to:
  http://localhost/phpmyadmin/index.php

  Click "New" and create a database named: kpi_system
  IMPORTANT: The database name must be exactly "kpi_system"

5. Import Database
   Select the kpi_system database.
   Go to the Import tab.
   Upload the file kpi_system.sql and click "Go".

6. Run the System
   Open your browser and go to:
   http://localhost/KPI_Management_System/

7. Login Credentials
   Username: supervisor@store.com
   Password: 1234

The system should now be ready to use.

/* QR CODE */
The KPI Management System uses dynamic QR codes for two main purposes:

1. Staff Profile QR (Podium Cards)
   - When scanned with any mobile QR reader, the code opens that staff member’s detailed profile page (staffprofile.php?id=...).

2. Report QR (PDF & Excel Preview)
   - On the Reports page, every generated report (PDF or Excel) has a small QR icon inside the export button.
   - Scanning it redirects the mobile device to the same report preview page (pdf_preview.php or excel_preview.php)


- The QR is generated on‑the‑fly using the QRserver API (https://api.qrserver.com/v1/create-qr-code/).

QR Generation Technical Details
   - API used: https://api.qrserver.com/v1/create-qr-code/ (free, no API key required).
   - Encoding: Full absolute URL (https://yourdomain.com/...) with encodeURIComponent().
   - Integration: QR codes are injected dynamically via JavaScript after report content loads, ensuring they always match the current filters.

Notes for QR Scanning
   - When running locally, your laptop and mobile phone must be connected to the same WiFi for QR codes to work.

