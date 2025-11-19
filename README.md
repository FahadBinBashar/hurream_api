# Hurream ERP Requirements & Implementation Guide

This document consolidates the functional scope, validation rules, approval flows, and implementation guidance for the Hurream web-based ERP platform. The specification is bilingual where appropriate so business stakeholders and engineers can reference the same source of truth.

## Table of Contents

1. [Purpose](#1-purpose)
2. [Scope](#2-scope)
3. [User Roles & Authentication](#3-user-roles--authentication)
4. [Admin & Policy Module](#4-admin--policy-module)
5. [Customer & Booking Module](#5-customer--booking-module)
6. [Employee & HR Module](#6-employee--hr-module)
7. [Sales & Marketing Module](#7-sales--marketing-module)
8. [Investment & Share Management](#8-investment--share-management)
9. [Accounts & Finance Module](#9-accounts--finance-module)
10. [Forms & Validation Rules](#10-forms--validation-rules)
11. [Core Workflows](#11-core-workflows)
12. [Dashboards & Reports](#12-dashboards--reports)
13. [Non-Functional Requirements](#13-non-functional-requirements)
14. [Suggested Tech Stack](#14-suggested-tech-stack)
15. [Acceptance / QA Checklist](#15-acceptance--qa-checklist)
16. [Traceability Matrix](#16-traceability-matrix)
17. [Backend API Implementation Notes](#17-backend-api-implementation-notes)

## Implementation Snapshot (Feb 2025)

- **Module A – Projects:** `/api/projects` exposes CRUD for project_id/code/name/location/status. Each project carries certificate prefixes and sequencing for automated certificate generation.
- **Module B – Share Batches:** `/api/share-batches` lets admin seed inventory per project with certificate ranges and FIFO-aware availability tracking.
- **Module C – Package Builder:** Updated `/api/share-packages` accepts `project_id`, `total_shares_included`, `bonus_shares`, `installment_months`, and structured benefits.
- **Module D – Share Sales:** `/api/sales/single` & `/api/sales/package` orchestrate project validation, FIFO inventory deduction, certificate/invoice generation, benefit snapshots, and share ledger entries.
- **Module E – Customers:** Unified `customers` table remains the anchor for investments, bookings, and dividends.
- **Module F – Installments:** `/api/installments/*` delivers due lists, schedules, and payments with voucher generation.
- **Module G – Booking System:** Existing booking endpoints continue to honour membership perks; discounts/free nights stored on share packages are available to booking flows.
- **Module H – HR:** Grades, designations, and employee endpoints backed by dedicated migrations/seeders.
- **Module I – Accounts:** Vouchers, ledger, cashbook/bankbook & stage reporting endpoints remain unchanged while receiving data from new share sales.
- **Module J – Dividend/Reinvest:** Stage tables continue to be updated via `/api/stages/*` with reinvest-flag carried from share issues.
- **Module K – Authentication & Roles:** JWT + OTP flow plus RBAC middleware wires every endpoint listed above.

---

## 1) Purpose

একটি পূর্ণাঙ্গ ওয়েব-ভিত্তিক ERP তৈরি করা যেখানে Sales/Share, Accounts/Finance, HR/Employee, Customer/Booking, Admin/Policy—সব এক প্ল্যাটফর্মে পরিচালিত হবে। Key policy-driven obligations include:

- ৩ সদস্যের অনুমোদন প্রক্রিয়া (multi-approver policy panel)।
- গ্রাহক এবং কর্মীর NID যাচাই ও ডকুমেন্টেশন।
- ধাপভিত্তিক আর্থিক পরিকল্পনা (MÖW) এবং লাভের ৫০% স্বয়ংক্রিয় পুনঃবিনিয়োগ।

## 2) Scope

- **Website (Public):** রুম/প্যাকেজ দেখা, অনলাইন বুকিং/পেমেন্ট, ইনভেস্টমেন্ট অফার প্রদর্শন।
- **Portal (Private):** Admin, Director/MD, Manager, Officer (Sales/Marketing), HR, Accounts, Investor, Customer।
- **Modules:** Admin & Policy, Customer/Booking, HR/Employee, Sales/Share, Investment/Profit, Accounts/Finance, Reports।

> **Design Update (Feb 2025):** Customer = Investor = Member. আলাদা `investors` টেবিল নেই; যে Customer শেয়ার কেনে তাকে `customers.is_investor = 1` হিসেবে ফ্ল্যাগ করা হয় এবং `investor_no` স্বয়ংক্রিয়ভাবে জেনারেট হয়। Shares, installments, stage-tracking সব জায়গায় এখন `customer_id` ব্যবহৃত হয়। ফলে একক প্রোফাইল থেকেই বুকিং + ইনভেস্টমেন্ট + ডিভিডেন্ড ট্র্যাক করা যায়।

## 3) User Roles & Authentication

| Role | Core Responsibilities |
|------|-----------------------|
| Admin | System configuration, policy management, approvals |
| Director / MD / Chairman | Executive approvals, strategic reports |
| Shareholder Director / ED / SED / AGM / DGM / GM / Deputy & Assistant Manager / Marketing Officer | গ্রেড ১–৬ ভিত্তিক দায়িত্ব ও অনুমতি (সেলস গ্রেড-৬ থেকে শুরু) |
| HR Officer | Employee lifecycle, attendance, payroll |
| Accounts / Finance | আয়-ব্যয়, ইনভয়েসিং, আর্থিক রিপোর্ট |
| Sales / Marketing Officer | লিড ম্যানেজমেন্ট, শেয়ার বিক্রয়, কালেকশন |
| Investor | হোল্ডিংস, কিস্তি, ডিভিডেন্ড পর্যবেক্ষণ |
| Customer | বুকিং, পেমেন্ট, ইনভয়েস |

Authentication requirements:

- ২-ফ্যাক্টর (OTP/SMS/Email) বাধ্যতামূলক Admin ও Finance ব্যবহারকারীর জন্য।
- স্ট্রং পাসওয়ার্ড নীতি ও OTP-ভিত্তিক পাসওয়ার্ড রিসেট।
- Role-based menu visibility ও granular RBAC ম্যাট্রিক্স।
- প্রত্যেক কার্যক্রমের জন্য অডিট লগ (কে/কখন/কি করলেন)।

## 4) Admin & Policy Module

### 4.1 নীতি ও অনুমোদন

- ৩ সদস্যের অনুমোদন প্যানেল: বড় সিদ্ধান্ত, শেয়ার ইস্যু, পেআউট, নীতি পরিবর্তন ইত্যাদিতে ন্যূনতম ৩ জন কর্তৃপক্ষের সম্মতি আবশ্যক।
- Policy Registry: চুক্তি/নীতির সংস্করণিং ও কার্যকর তারিখ সংরক্ষণ।
- Role Matrix: Role × Module × Action (Create/Read/Update/Delete/Approve) কনফিগারেশন।

### 4.2 কনফিগারেশন সেটিংস

- শেয়ার ইউনিট মূল্য (ডিফল্ট ২৫,০০০ টাকা — ওভাররাইডেবল)।
- কিস্তি স্কিম (মেয়াদ, কিস্তি পরিমাণ)।
- ডিভিডেন্ড রেট ও পিরিয়ড (মাসিক/ত্রৈমাসিক/বার্ষিক)।
- কমিশন রেট (রোল বা অফিসার ভিত্তিক)।
- SMS/Email নোটিফিকেশন টেমপ্লেট।

## 5) Customer & Booking Module

### 5.1 রেজিস্ট্রেশন ও ভেরিফিকেশন

- Customer প্রোফাইল ফিল্ড: নাম, মোবাইল, ইমেইল, ঠিকানা, NID (আবশ্যক), রেফারেন্স।
- ডকুমেন্ট আপলোড: NID কপি ও ফটো।
- স্ট্যাটাস লাইফসাইকেল: New → Verified → (ঐচ্ছিক) Blacklisted।

### 5.2 বুকিং লাইফসাইকেল

- তারিখ, অতিথি সংখ্যা ও টাইপ অনুযায়ী রুম সার্চ।
- সিজনাল প্রাইসিং, কুপন, অ্যাভেইলেবিলিটি চেক।
- অগ্রিম বা পূর্ণ পেমেন্ট (Bkash, Nagad, Card, Bank)।
- কনফার্মেশন SMS/Email ও ইনভয়েস PDF।
- বাতিল/রিফান্ড নীতি কনফিগারেশন।

### 5.3 রিপোর্টিং

- দিন/মাস/টাইপভিত্তিক বুকিং সারাংশ।
- রিফান্ড রিপোর্ট।
- কাস্টমার লাইফটাইম ভ্যালু অ্যানালিটিক্স।

## 6) Employee & HR Module

### 6.1 কর্মী প্রোফাইল

- আবশ্যক ফিল্ড: নাম, পিতা/মাতা, NID, ঠিকানা, মোবাইল, ইমেইল, ন্যূনতম SSC/সমমান শিক্ষাগত যোগ্যতা, যোগ্যতা বিবরণ, ছবি, যোগদান তারিখ, গ্রেড/পদবি।
- ডকুমেন্টেশন: NID, পুলিশ ভেরিফিকেশন, রেফারেন্স, প্রয়োজনে PVS ID।
- ডকুমেন্ট চেকলিস্ট ও অনুমোদন পর্যায়।

### 6.2 গ্রেড ও KPI

- গ্রেড ১–৬ দায়িত্ব ও KPI ম্যাপিং।
- মার্কেটিং অফিসার (গ্রেড-৬): লিড → শেয়ার সেলস → কালেকশন।

### 6.3 উপস্থিতি ও বেতন

- Biometric/Web check-in সহ লেট/ওভারটাইম নিয়মাবলি।
- Salary = Base + Allowance + Commission (সেলস ভূমিকা)।
- Leave Policy (Casual/Sick/Earn)।
- পারফরম্যান্স রিভিউ ও ইনসেনটিভ।

### 6.4 Grade & Designation Management (Module 11)

- **Grade Master:** Admin panel থেকে Grade 1–6 (Director → Officer) পর্যন্ত ক্রমিক তালিকা তৈরি/এডিট/ডিলিট করা যায়। `grades` টেবিলে `grade_no`, `grade_name`, `description`, `status` ফিল্ড থাকে এবং Dashboard-এ Active/Inactive কাউন্ট ও সাম্প্রতিক পরিবর্তন দেখা যায়।
- **Designation Master:** প্রত্যেক Grade-এর অধীনে একাধিক Designation (`designations` টেবিল) ম্যাপ করা হয়। একই Grade-এ ডুপ্লিকেট নাম নিষিদ্ধ এবং Inactive পদবি HR ফর্মে দেখানো হয় না। Admin পুরো CRUD করতে পারে; HR Manager শুধুমাত্র Designation create/update করতে পারে; অন্যান্য রোলের জন্য রিড-অনলি।
- **API Workflow:**
  - `GET /api/grades` → Grade লিস্ট + স্ট্যাটস।
  - `POST/PUT /api/grades/{id}` → Admin grade তৈরি/আপডেট।
  - `GET /api/grades/{id}/designations` → নির্বাচিত grade-এর Active Designation ড্রপডাউন।
  - `GET /api/designations?grade_id=6` এবং `POST/PUT /api/designations/{id}` → HR/Admin Designation ম্যানেজমেন্ট।
- **Employee Form Integration:** Add Employee করার সময় প্রথমে Grade নির্বাচন করা বাধ্যতামূলক, তারপর ঐ Grade-এর Active Designation গুলো অটো লোড হয়। Backend ভ্যালিডেশন নিশ্চিত করে যে নির্বাচিত Designation সেই Grade-এর অন্তর্ভুক্ত এবং Inactive নয়।
- **Promotion / History:** Grade ও Designation `employees` টেবিলে রিলেশন আকারে সংরক্ষণ হওয়ায় ভবিষ্যতে Promotion workflow, Audit log এবং Dashboard মেট্রিক্স একই ডেটা উৎস থেকে পাওয়া যায়।

## 7) Sales & Marketing Module

### 7.1 লিড / CRM

- Lead ফিল্ড: নাম, মোবাইল, উৎস, বাজেট, আগ্রহ, স্ট্যাটাস (Lead → Prospect → Investor)।
- অটো রিমাইন্ডার ও ফলো-আপ নোট।

### 7.2 শেয়ার সেলস

- প্যাকেজ: Unit Price, Quantity, Payment Mode কনফিগারেশন।
- শেয়ার সার্টিফিকেট (PDF + QR) অটো-জেনারেশন।
- কমিশন ক্যালকুলেশন (Configurable rules)।
- বড় বরাদ্দে মাল্টি-স্টেপ সেলস অনুমোদন (৩-Approver নীতির সাথে সিঙ্ক)।

### 7.3 রিপোর্ট

- Officer-wise Sales/Collection।
- Conversion Rate বিশ্লেষণ।
- Commission Payable ট্র্যাকিং।

## 8) Investment & Share Management

### 8.1 Investor Registration

- প্রোফাইল ফিল্ড: নাম, NID, মোবাইল, ইমেইল, ঠিকানা, ব্যাংক তথ্য, Nominee।
- ভেরিফিকেশন: OTP + Admin Approval।
- Investor Dashboard: Holdings, Payments, Dividends।

### 8.2 Share Issue & Allocation

- শেয়ার ইউনিট মূল্য (ডিফল্ট ২৫,০০০), Quantity, Issue Date।
- Payment Plan: এককালীন অথবা কিস্তি।
- Share Ledger, Certificate, Transfer ট্র্যাকিং।

### 8.3 ধাপভিত্তিক আর্থিক পরিকল্পনা (MÖW)

- কনফিগারেবল স্টেজ টেবিল (যেমন MÖW-5, MÖW-4)।
- লাভ বা টপ-আপ যুক্ত হলে নতুন ধাপের মূলধন অটো হিসাব।
- লাভের ৫০% পুনঃবিনিয়োগ (On/Off) পরবর্তী ধাপ/ছয় মাসে অটো প্রয়োগ।

### 8.4 Share Product & Package Management Module

> **Why this matters:** Brochure-এ ঘোষিত শেয়ার অফার, কিস্তি প্ল্যান, ফ্রি স্টে, ডিসকাউন্ট, বোনাস শেয়ার, ট্যুর ভাউচার ইত্যাদি বিক্রয় টিমের জন্য প্রি-কনফিগার না থাকলে প্রতিবার হাতে হিসাব করতে হয়। তাই ERP-এ একটি সম্পূর্ণ Share Product & Package Module আবশ্যক।

#### 8.4.1 Single Share Product

- Admin `share_products` টেবিলে একটি সক্রিয় রেকর্ড মেইনটেইন করবে যেখানে `share_unit_price` (ডিফল্ট ২৫,০০০ টাকা) ও স্ট্যাটাস থাকবে।
- সিস্টেমে একক শেয়ার স্টকের সীমাবদ্ধতা নেই; Sales Officer যেকোনো সময় ১× ইউনিট মূল্য দিয়ে সেল প্রসেস করতে পারবে।
- ভবিষ্যতে যদি ইউনিট মূল্য পরিবর্তন হয়, কার্যকর তারিখ ধরে ইতিহাস রাখা হবে যাতে পুরোনো সেল রেকর্ড সঠিক থাকে।

#### 8.4.2 Share Packages (Predefined Bundles)

- Admin ড্যাশবোর্ডে প্যাকেজ বিল্ডার থাকবে যেখানে নিচের ফিল্ডগুলো বাধ্যতামূলক:
  - `package_name` (Silver, Gold, Platinum ইত্যাদি)।
  - `base_price` (যেমন ৭,২০,০০০ টাকা)।
  - `total_shares` বা Auto-calculated = `base_price ÷ share_unit_price`।
  - `monthly_installment`, `duration_months`, `down_payment` (ঐচ্ছিক)।
  - `bonus_share_qty` (% বা qty), `package_upgrade_policy`।
  - সুবিধাসমূহ: `free_nights`, `lifetime_discount`, `international_tour_voucher`, `gift_items`, `service_voucher`, ইত্যাদি।
- Admin `package_benefits` সাব-টেবিলের মাধ্যমে অতিরিক্ত বেনিফিট টাইপ/ভ্যালু অ্যাটাচ করতে পারবে (যেমন `benefit_type = "restaurant_discount"`, `benefit_value = "30%"`).
- প্যাকেজ স্টেটাস Active/Inactive; inactive প্যাকেজ সেলস ফর্মে দেখাবে না।

#### 8.4.3 Installment & Bonus Logic

- প্রতিটি প্যাকেজে কিস্তি কনফিগারেশন প্রি-ডিফাইনড থাকবে (`duration_months`, `monthly_installment`, `grace_period_days`).
- সিস্টেম `base_price` থেকে ডাউনপেমেন্ট বাদ দিয়ে বাকি অঙ্ককে কিস্তিতে ভাগ করে ইনভয়েস শিডিউল জেনারেট করবে।
- `bonus_share_qty` বা `%` এর ভিত্তিতে মোট শেয়ার গণনায় `total_shares = base_share_qty + bonus_share_qty` হিসেবে সংরক্ষণ হবে।

#### 8.4.4 Sales Officer Workflow

1. Sales Officer গ্রাহক নির্বাচন করবে (Existing Customer অথবা নতুন রেজিস্ট্রেশন + KYC)।
2. `Single Share` অথবা `Share Package` সিলেক্ট করবে; প্যাকেজ লিস্টে বেস প্রাইস, মোট শেয়ার, কিস্তি, সুবিধা দেখাবে।
3. সিস্টেম অটো-ক্যালকুলেশন:
   - মোট শেয়ার, বোনাস শেয়ারসহ।
   - মোট পে-যোগ্য (ডাউনপেমেন্ট + বাকি)।
   - কিস্তি শিডিউল ও পরবর্তী কিস্তির তারিখ।
   - সুবিধার সারাংশ (ফ্রি স্টে, ডিসকাউন্ট, ভাউচার, গিফট বক্স, মেম্বার কার্ড)।
4. বিক্রয় কনফার্ম করলে `customer_shares` টেবিলে এন্ট্রি হবে (`customer_id`, `package_id` nullable, `single_share_qty`, `bonus_share_qty`, `total_shares`, `payment_plan`).
5. সিস্টেম ইনভয়েস + শেয়ার সার্টিফিকেট (QR সহ) জেনারেট করবে এবং Customer Portal-এ প্যাকেজের ডিটেইল দেখাবে।

#### 8.4.5 Backend Data Model

| Table | Key Fields | Purpose |
|-------|------------|---------|
| `share_products` | `unit_price`, `effective_from`, `status` | বর্তমান একক শেয়ার প্যারামস ও ইতিহাস। |
| `share_packages` | `package_name`, `base_price`, `total_shares`, `monthly_installment`, `duration_months`, `bonus_share_qty`, `benefit_summary` | প্যাকেজের মূল কনফিগারেশন। |
| `package_benefits` | `package_id`, `benefit_type`, `benefit_value`, `notes` | বহুবিধ সুবিধা ম্যাপিং (ফ্রি নাইট, ডিসকাউন্ট, ট্যুর ভাউচার ইত্যাদি)। |
| `customer_shares` | `customer_id`, `package_id`, `single_share_qty`, `bonus_share_qty`, `total_shares`, `down_payment`, `installment_plan`, `status` | বিক্রয় রেকর্ড; প্যাকেজ বা সিঙ্গেল শেয়ার উভয় ট্র্যাক করবে। |

#### 8.4.6 Customer & Reporting Views

- Customer Portal-এ Package Card গুলোতে `remaining_installments`, `benefits_claimed`, `certificate_download` অপশন থাকবে।
- Sales Dashboard-এ `Package-wise Sales`, `Bonus Share Liability`, `Upcoming Installments` রিপোর্ট প্রয়োজন।
- প্যাকেজ সুবিধা যেমন ফ্রি স্টে/ট্যুর ভাউচার রিডিম হলে অডিট লগ আপডেট হবে যাতে Accounts ও Operations টিম ক্লেইম যাচাই করতে পারে।

### 8.5 Profit Distribution (Dividend)

- Periodic disbursement (Monthly/Quarterly/Yearly)।
- Share proportion অনুযায়ী লাভ বণ্টন।
- Tax/Charges অটো কাট-অফ।
- Disbursement via Bank/MFS + Advice/Slip জেনারেশন।
- Investor-wise Dividend History।

## 9) Accounts & Finance Module

### 9.1 আয়/ব্যয়/কিস্তি

- Daily Cash/Bank Book।
- Installment Schedule ও Reminder সিস্টেম।
- Voucher টাইপ: Receipt, Payment, Journal।

### 9.2 আর্থিক ধাপ ও সংখ্যা

- কনফিগারেবল Capital/Bonus ধাপ এবং Target vs Achieved রিপোর্ট।
- স্টেজ নাম/তারিখসহ আর্থিক প্রগ্রেস ট্র্যাকিং।

### 9.3 রিপোর্টিং

- Trial Balance, Income Statement, Balance Sheet।
- Cashflow ও Bank Reconciliation।
- Entity/Action/Time/IP সহ অডিট লগ।

## 10) Forms & Validation Rules

### 10.1 Customer Form

- Required: নাম, মোবাইল, NID, ঠিকানা।
- Optional: ইমেইল, রেফারেন্স।
- NID format + ফাইল আপলোড বাধ্যতামূলক; ডুপ্লিকেট NID ব্লক।
- স্ট্যাটাস: New → Verified (ডক চেকের পর)।

### 10.2 Employee Form

- Required: নাম, মোবাইল, NID, ঠিকানা, SSC/সমমান শিক্ষাগত তথ্য, গ্রেড।
- Docs: NID, Police Verification, Photo; প্রয়োজনে PVS ID।

### 10.3 Investor Form

- Required: নাম, NID, মোবাইল, ইমেইল, ঠিকানা।
- অতিরিক্ত: Bank Account, Nominee।
- OTP যাচাই ও Admin Approval।

### 10.4 Share Issue Form

- ফিল্ড: Investor, Unit Price (ডিফল্ট ২৫,০০০), Quantity, Payment Mode (One-time/Installment), Stage (MÖW), Approver(s)।
- Validation: Amount = Unit × Quantity; বড় বরাদ্দে ৩-Approver Gate ট্রিগার।

### 10.5 Booking Form

- Date In/Out, Guest Count, Room Type, Price Plan, Payment Details।

## 11) Core Workflows

### 11.1 শেয়ার সেলস

1. Officer লিড এন্ট্রি করে Prospect-এ রূপান্তর।
2. Proposal/Brochure প্রেরণ।
3. Investor Registration → Verification।
4. Share Issue (Qty/Price) → Approval Flow (flagged হলে ৩-Approver)।
5. Payment (One-time/Installment)।
6. Certificate & Receipt জেনারেশন।
7. কমিশন স্বয়ংক্রিয় গণনা।

### 11.2 MÖW Stage-Based Fund Growth

1. Stage Config (MÖW-5/4/6 ...)।
2. Period Close → Profit Add → ৫০% Reinvest (যদি Enabled)।
3. Next Stage Capital অটো কম্পিউট।
4. রিপোর্ট আপডেট।

### 11.3 বুকিং

1. Search → Select Room → Pay → Confirm।
2. Invoice + SMS/Email ডেলিভারি।
3. Cancel/Refund পলিসি অনুসারে প্রসেস।

## 12) Dashboards & Reports

- **Admin:** Approvals pending, Policy changes, Audit trail।
- **Director/MD:** Stage-wise Capital, Profit, Sales, Booking সারাংশ।
- **Sales Manager:** Officer-wise Sales/Commission মেট্রিক্স।
- **HR:** Attendance, Salary, Grade Movement।
- **Accounts:** Trial Balance, Income Statement, Balance Sheet, Cashflow, Reconciliation।
- **Investor:** Holdings, Payments, Dividends।
- **Customer:** Bookings, Invoices।

## 13) Non-Functional Requirements

- Security: RBAC, Encryption, SSL, 2FA।
- Performance: ১০০০+ concurrent users লক্ষ্য।
- Availability: 99.9% আপটাইম টার্গেট।
- Backup: দৈনিক স্বয়ংক্রিয় এবং অন-ডিমান্ড।
- Localization: বাংলা ও ইংরেজি ইন্টারফেস।
- Logging: ১ বছর অনলাইন, আর্কাইভ ৫ বছর।

## 14) Suggested Tech Stack

- Frontend: React.js + Tailwind CSS।
- Backend: Laravel বা Django REST।
- Database: MySQL বা PostgreSQL।
- Authentication: JWT / OAuth2।
- Reporting: PDF ও Excel Export।

## 15) Acceptance / QA Checklist

- 3-Approver flow সব flagged অপারেশনে কার্যকর।
- Customer/Employee NID বাধ্যতামূলক এবং ডুপ্লিকেট ব্লক।
- MÖW stage configuration ও ৫০% reinvest অটো টপ-আপ সঠিকভাবে কাজ করে।
- Share certificate ও receipt জেনারেশন।
- Booking → Pay → Confirm → Cancel/Refund সম্পূর্ণ ফ্লো সফল।
- Role-wise permission matrix যাচাই করা।

## 16) Traceability Matrix

| Req ID | বর্ণনা | উৎস/ফাইল | ম্যাপড স্ক্রিন/প্রসেস |
|--------|--------|-----------|------------------------|
| R-ADM-01 | ৩ সদস্য অনুমোদন প্রক্রিয়া | অন্যান্য.pdf | Approval Settings, Issue Approvals |
| R-CUS-02 | Customer NID বাধ্যতামূলক | গ্রাহক.pdf | Customer Form + Verification |
| R-EMP-03 | Employee SSC ও ডকুমেন্ট যাচাই | কর্মী.pdf | Employee Form + Doc Checklist |
| R-INV-04 | ধাপভিত্তিক লাভ বণ্টন ও ৫০% পুনঃবিনিয়োগ | কর্মী প্ল্যান.pdf | Stage Engine + Close Period |
| R-MOW-05 | ধাপ/লাভ-টপআপ ও ৫০% reinvest | Stage Policy Document | Stage Engine Automation |

## 17) Backend API Implementation Notes

`/hphrms_api` ডিরেক্টরিতে ইতিমধ্যে একটি সম্পূর্ণ PHP-ভিত্তিক REST API রয়েছে যা উপরের মডিউলার কাঠামো অনুসরণ করে। উল্লেখযোগ্য দিকসমূহ:

- Token ভিত্তিক Authentication সহ Multi-role enforcement (Admin, HR, Accounts, Officer, Investor, Customer)।
- Customers, bookings, investors, employees, leads, approvals, accounts, shares এবং transactions এর জন্য CRUD API।
- বুকিং cancellation/refund ফ্লো এবং sales, investment ও finance-এর জন্য aggregated রিপোর্ট।
- সহজ সেটআপের জন্য SQLite-সমর্থিত মাইগ্রেশন ও সিডিং টুলিং (`php artisan migrate`, `php artisan db:seed`)।
- Postman collection: `hphrms_api/docs/hphrms_postman_collection.json`।

### Quick Start

```bash
cd hphrms_api
cp .env.example .env
php artisan migrate
php artisan db:seed
php -S localhost:8000 -t public
```

Seeded administrator account: `admin@hphrms.test` / `password` (login via `/api/auth/login`)। এরপর Postman collection অনুসরণ করুন।

> **Note:** Implementation টি framework-lightweight; সমস্ত ক্লাস `App\` namespace-এ এবং `vendor/autoload.php` এর মাধ্যমে অটোলোড হয়, ফলে সীমিত পরিবেশেও রান করা যায়।

