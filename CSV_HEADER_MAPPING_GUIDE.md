# CSV Import Header Mapping Guide

## Issue
Your CSV file contains headers that don't match the expected format for the CSIMS member import system.

## Your Current Headers vs Expected Headers

| Your Header | Expected Header | Required | Notes |
|-------------|-----------------|----------|-------|
| timestamp | - | No | Remove this column |
| middle_name | - | No | Not supported, combine with first_name if needed |
| sex | gender | No | Change to: Male, Female, or Other |
| personal file no | ippis_no | No | Rename column |
| mobile phone number | phone | No | Rename column |
| posting (office) | - | No | Not supported, can be added to address |
| department | - | No | Not supported |
| date of birth | date_of_birth | No | Rename column, format as YYYY-MM-DD |
| marital status | - | No | Not supported |
| e-mail address | email | Yes | Rename column |
| highest educational qualification | - | No | Not supported |
| adress of residence | address | No | Fix spelling: address |
| date of first appointment | - | No | Not supported |
| date of present appointment | - | No | Not supported |
| date of retirement | - | No | Not supported |
| grade level | - | No | Not supported |
| ippis no | ippis_no | No | Already correct |
| next of kin | - | No | Not supported |
| account name | - | No | Not supported |
| bank name (for payment of dividen/welfare) | - | No | Not supported |
| bank account number | - | No | Not supported |
| savngs or current | - | No | Not supported |

## Required Headers (Minimum)
- `first_name` (Required)
- `last_name` (Required) 
- `email` (Required)

## Optional Headers
- `phone`
- `gender` (Male, Female, or Other)
- `date_of_birth` (YYYY-MM-DD format)
- `address`
- `membership_type_id` (1 for Basic, 2 for Premium, 3 for Gold)
- `ippis_no`
- `username` (auto-generated if not provided)
- `password` (auto-generated if not provided)

## How to Fix Your CSV

1. **Create a new CSV file** with only the supported headers
2. **Map your data** according to the table above
3. **Remove unsupported columns** (timestamp, middle_name, department, etc.)
4. **Rename columns** to match expected headers
5. **Format data correctly**:
   - Gender: Male, Female, or Other
   - Date of birth: YYYY-MM-DD format
   - Email: valid email format

## Example Correct CSV Format

See the file `sample_members_correct_format.csv` for a properly formatted example.

## Steps to Import

1. Fix your CSV file according to this guide
2. Log in to CSIMS admin panel
3. Go to Members section
4. Use the Import function
5. Upload your corrected CSV file

## Need Help?

If you need to preserve data from unsupported columns, consider:
- Adding relevant information to the `address` field
- Importing basic member data first, then updating records manually with additional information
- Contacting system administrator for custom import solutions