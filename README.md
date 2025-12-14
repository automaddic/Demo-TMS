- PROJECT DEMO SITE
- IMPORTANT NOTE
  - This repository is a demo / stripped-down version of a larger production system originally built for a non-profit organization.
  - Exists for demonstration, review, and testing purposes only.
  - If errors, bugs, or unexpected behavior are found, create an Issue on the GitHub repository.

- FEATURES REMOVED OR DISABLED FROM THE ORIGINAL SITE
  - Google Sheets integration removed
  - Slack bot import system removed
  - Accounts modal not available
  - Login without an account disabled
  - Reset password flow disabled
  - Additional automation tools and workflows removed or simplified

- DISCLAIMER
  - No .env files included
  - No .json configuration or credential files included
  - All secrets, API keys, and sensitive configuration removed
  - All user data is minimal and fake
  - Fake user data generated using ChatGPT
  - Original system built for real organization with real users
  - Demo version intentionally dumbed down and sanitized

- AUTHENTICATION AND ACCOUNTS
  - LOGIN
    - Click Login button to sign in using demo admin account
    - No credentials required
  - CREATE ACCOUNT
    - Use “Don’t have an account” option to create test account
    - Demonstrates basic security feature
    - System checks submitted info against spreadsheet (fake data)
    - IMPORTANT: Use fake email, username, and password

- HOME PAGE
  - Weather for current day based on GPS
  - List of upcoming practice days
  - Each practice day includes:
    - Three RSVP buttons
    - Directions button linking to Google Maps

- ENLARGED PRACTICE DAY VIEW
  - Expanded event details
  - Weather for specific day via Google Maps latitude/longitude
  - Coach notes set by coaches

- RSVP PAGE
  - Displays users attending a practice day
  - Shows RSVP status for each user

- PRACTICE DAYS PAGE
  - Create new practice days (name, start date, start time required)
  - Weather data requires Google Maps link
  - Repeat practice days by day of week (up to 16 weeks)
  - Edit existing practice days
  - Delete practice days

- ADMIN PAGE
  - Page access management
  - Role level management for access control
  - Various data entry editors
  - WARNING: Removing Schools, Teams, or Ride Groups may break system

- RIDE GROUPS PAGE
  - Edit ride group assignments for users
  - Set or update user’s ride group

- COACH NOTES PAGE
  - Create and edit coach notes for ride groups
  - Workflow:
    - Select ride group
    - Click practice day
    - View or edit coach notes

- SUSPENSIONS PAGE
  - Displays users flagged by security system
  - Allows admins to view/edit suspended users
  - Uses same validation logic as account creation

- IMPORT USERS PAGE
  - Import users from spreadsheet
  - Demo features:
    - Selective import of individual users
    - Mass import of users
  - Missing/disabled features:
    - Automatic Slack import
    - Export to Google Sheets
    - Update spreadsheet buttons

- CHECK-INS PAGE
  - Manage attendance during practice days
  - Check users in/out
  - Sort users with options
  - Reset check-in data (single user or all)
  - End practice day
  - Manually check in users
  - Clicking user’s name reveals:
    - Phone number
    - Emergency contact info
    - Medical info

- FINAL NOTES
  - Project for demonstration/evaluation only
  - Sensitive integrations removed
  - All data fake and safe to use
  - Report bugs/issues via GitHub Issues
  - Please go to db.tms-demo.maddicjordan.com for the database architecture
    - login credentials
    - username: demo_viewer_tms
    - password: demonstration
