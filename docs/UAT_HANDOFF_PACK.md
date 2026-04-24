# UAT Handoff Pack

Generated on 21 Apr 2026

## Purpose

Use this guide when you are about to give the system to one supermarket owner for testing.

This testing method uses:

- one shared app
- one public/ngrok link at a time
- one tester at a time

## Before You Send The Link

1. Confirm the app is running.
2. Confirm the ngrok/public link is active.
3. Decide which owner is testing now.
4. Make sure you are sending only that owner's accounts.
5. Do not send the `admin` account to the tester.

## Files To Send

### If Owner A Is Testing

Send these files:

- `docs/OWNER_A_ACCESS_SHEET.docx`
- `docs/OWNER_A_TEST_MESSAGE.docx`
- `docs/OWNER_TESTING_SOP.docx`

### If Owner B Is Testing

Send these files:

- `docs/OWNER_B_ACCESS_SHEET.docx`
- `docs/OWNER_B_TEST_MESSAGE.docx`
- `docs/OWNER_TESTING_SOP.docx`

## Message To Send With The Link

Use this short message:

Hello. Your test session for the new supermarket system is ready.

Please use the link below together with the login details in the attached access sheet.

Test link:
[paste current ngrok/public link]

Please start with the SOP guide, then test the normal daily flow:

1. review products and stock
2. make one purchase
3. make one sale
4. print a receipt or invoice
5. check statements and reports

As you test, please note:

- what was easy
- what was confusing
- what was missing
- what should be improved before real use

## Day 1 Testing Flow

Ask the tester to go through this order:

1. Log in with their assigned account.
2. Check products and stock balances.
3. Create a purchase and confirm stock increased.
4. Create a cash sale and print the receipt.
5. Create a credit sale if they use customer credit.
6. Receive a customer payment if needed.
7. Open customer or supplier statements.
8. Review dashboard and reports.

## What You Should Watch For

As the tester uses the system, collect feedback in four groups:

- confusing labels or wording
- missing workflow steps
- print or report problems
- mobile or small-screen layout issues

## After The Session

1. Write down all feedback clearly.
2. Group issues into urgent, medium, and minor.
3. Fix urgent workflow issues first.
4. Only after that, give access to the next tester.

## Internal Note

This is still one shared testing database.

That means:

- only one tester should be active at a time
- sample data remains in the same environment
- you should avoid overlapping test sessions
