১) উদ্দেশ্য (Purpose)
একটি পূর্ণাঙ্গ ওয়েব-ভিত্তিক ERP তৈরি করা যা Sales, HR, Accounts, Investment, এবং Booking পরিচালনা করবে।
২) স্কোপ (Scope)
Website: Booking ও Investment View, Portal: Admin, Director, Manager, HR, Accounts, Sales, Investor, Customer লগইন সুবিধা।
৩) ইউজার রোল ও লগইন
Admin, Director, Manager, Officer, HR, Accounts, Investor, Customer রোল থাকবে; প্রত্যেকের আলাদা পারমিশন।
৪) Admin & Policy Module
৩ সদস্য অনুমোদন, নীতি সংরক্ষণ, কমিশন রেট, ডিভিডেন্ড রেট, শেয়ার ইউনিট মূল্য নির্ধারণ।
৫) Customer & Booking Module
গ্রাহক রেজিস্ট্রেশন, রুম সার্চ, বুকিং, পেমেন্ট গেটওয়ে, রিফান্ড, রিভিউ।
৬) Employee & HR Module
কর্মী রেজিস্ট্রেশন, গ্রেড ১–৬, উপস্থিতি, বেতন, ইনসেনটিভ, পারফরম্যান্স রিপোর্ট।
৭) Sales & Marketing Module
লিড ট্র্যাকিং, শেয়ার বিক্রয়, কমিশন ক্যালকুলেশন, রিপোর্ট ও অনুমোদন।
৮) Investment & Share Management
Investor Registration, Share Issue, Stage-based (MÖW) প্ল্যান, ৫০% পুনঃবিনিয়োগ, লাভ বণ্টন।
৯) Accounts & Finance Module
দৈনিক আয়-ব্যয়, ইনভয়েস, কিস্তি, ব্যালেন্স রিপোর্ট, অডিট লগ।
১০) ফর্ম ফিল্ড ও ভ্যালিডেশন
Customer (NID বাধ্যতামূলক), Employee (SSC/Docs), Investor (Nominee/Bank), Share (3-Approver Gate)।
১১) Workflow
Sales: Lead→Proposal→Investor→Approval→Payment; Booking: Search→Pay→Confirm→Cancel; Investment: Stage Update→Reinvest।
১২) রিপোর্ট/ড্যাশবোর্ড
Admin, HR, Accounts, Sales, Investor, Customer Dashboard ও রিপোর্ট এক্সপোর্ট (PDF/Excel)।
১৩) Non-Functional Requirements
Security: RBAC, SSL; Users; Backup: Daily Auto; 
১৪) Tech Stack
Frontend: React.js; Backend: Laravel/Django; DB: MySQL/PostgreSQL; Auth: JWT; Reports: PDF/Excel।
১৫) QA Checklist
3-Approver flow, NID Validation, Stage Config, Booking Refund, Role Permission Matrix Pass।
১৬) Traceability Table
Req ID	Description	Source/Reference
R-ADM-01	৩ সদস্য অনুমোদন প্রক্রিয়া	অন্যান্য.pdf
R-CUS-02	গ্রাহক NID বাধ্যতামূলক	গ্রাহক.pdf
R-EMP-03	কর্মী SSC ও ডকুমেন্ট যাচাই	কর্মী.pdf
R-INV-04	ধাপভিত্তিক লাভ বণ্টন ও ৫০% পুনঃবিনিয়োগ	কর্মী প্ল্যান.pdf

 
১) উদ্দেশ্য (Purpose)
এই সিস্টেমের উদ্দেশ্য হলো একটি ওয়েব-ভিত্তিক ERP তৈরি করা যেখানে Sales/Share, Accounts/Finance, HR/Employee, Customer/Booking, Admin/Policy—সব এক প্লাটফর্মে চলবে।
ফাইলভিত্তিক বাধ্যতামূলক নীতিমালা সিস্টেমে বলবৎ থাকবে—যেমন ৩ সদস্যের অনুমোদন (policy/approval panel), গ্রাহকের NID/ভেরিফিকেশন, ধাপে ধাপে আর্থিক পরিকল্পনা, লাভের ৫০% পুনঃবিনিয়োগ ইত্যাদি।
________________________________________
২) স্কোপ (Scope)
•	Website (Public): রুম/প্যাকেজ দেখা, অনলাইন বুকিং/পেমেন্ট, ইনভেস্টমেন্ট অফার দেখানো
•	Portal (Private): Admin, Director/MD, Manager, Officer (Sales/Marketing), HR, Accounts, Investor, Customer
•	Modules: Admin & Policy, Customer/Booking, HR/Employee, Sales/Share, Investment/Profit, Accounts/Finance, Reports
________________________________________
৩) ইউজার রোল ও লগইন (User Roles & Login)
রোল:
1.	Admin – সিস্টেম কনফিগ/নীতি/অনুমোদন
2.	Director/MD/Chairman – উচ্চস্তরের অনুমোদন/রিপোর্ট
3.	Shareholder Director / ED/SED / AGM/DGM/GM / Deputy/Asst. Manager / Marketing Officer – গ্রেডভিত্তিক ক্ষমতা (গ্রেড ১–৬) (নিয়োগ/সেলস স্টার্ট গ্রেড-৬)
4.	HR Officer – কর্মী/গ্রেড/উপস্থিতি/বেতন
5.	Accounts/Finance – আয়/ব্যয়/ইনভয়েস/রিপোর্ট
6.	Sales/Marketing Officer – লিড/শেয়ার সেলস/কালেকশন
7.	Investor – শেয়ার, কিস্তি/ডিভিডেন্ড দেখবেন
8.	Customer (Guest) – বুকিং/পেমেন্ট, ইনভয়েস
গ্রেড কাঠামো ও সেলস শুরু গ্রেড-৬ (মার্কেটিং অফিসার) থেকে—প্রক্রিয়া এবং পারমিশনে প্রতিফলিত হবে। (ছবিভিত্তিক তথ্য)
লগইন নিয়মাবলি (সকল রোল):
•	২-ফ্যাক্টর (OTP/SMS/Email) – বাধ্যতামূলক Admin/Finance-এ
•	পাসওয়ার্ড স্ট্রেংথ, রিসেট OTP
•	রোল-ভিত্তিক মেনু/ফিচার ভিজিবিলিটি
•	অডিট লগ: কে/কখন/কি করলেন—সব ট্র্যাক
________________________________________
৪) Admin & Policy Module (ফাইলভিত্তিক নীতিমালা)
৪.১ নীতি ও অনুমোদন (Policy & Approvals)
•	৩ সদস্যের অনুমোদন প্যানেল: বড় সিদ্ধান্ত/ইস্যু রিকুইজিশন/পেআউট/নীতি পরিবর্তনে ন্যূনতম ৩ জন কর্তৃপক্ষের সম্মতি লাগবে; সিস্টেমে multi-approver workflow থাকবে।
•	Policy Registry: চুক্তি/নীতির সংস্করণিং, কার্যকর তারিখ
•	Role Matrix: রোল × মডিউল × অ্যাকশন (Create/Read/Update/Delete/Approve)
৪.২ কনফিগারেশন
•	শেয়ার ইউনিট মূল্য (ডিফল্ট ২৫,০০০ টাকা—ওভাররাইডেবল)
•	কিস্তি স্কিম (মেয়াদ/কিস্তি-পরিমাণ)
•	ডিভিডেন্ড রেট/পিরিয়ড (মাসিক/ত্রৈমাসিক/বার্ষিক)
•	কমিশন রেট (সেলস/অফিসার-ভিত্তিক)
•	নোটিফিকেশন টেমপ্লেট (SMS/Email)
________________________________________
৫) Customer & Online Booking Module
৫.১ রেজিস্ট্রেশন/ভেরিফিকেশন (ফাইল শর্ত)
•	Customer Profile ফিল্ড: নাম, মোবাইল, ইমেইল, ঠিকানা, NID (আবশ্যক), রেফারেন্স।
•	ডকুমেন্ট আপলোড: NID/ফটো
•	স্ট্যাটাস: New/Verified/Blacklisted
৫.২ বুকিং
•	রুম সার্চ (তারিখ/অতিথি সংখ্যা/টাইপ)
•	রুম অ্যাভেইলেবিলিটি/প্রাইসিং (সিজন/কুপন)
•	অগ্রিম/পূর্ণ পেমেন্ট (Bkash/Nagad/Card/Bank)
•	কনফার্মেশন SMS/Email + ইনভয়েস PDF
•	বাতিল/রিফান্ড নীতি (কনফিগারেবল)
৫.৩ রিপোর্ট
•	বুকিং সারাংশ (দিন/মাস/টাইপ)
•	রিফান্ড রিপোর্ট
•	কাস্টমার লাইফটাইম ভ্যালু
________________________________________
৬) Employee & HR Module (কর্মী নির্দেশিকা-ভিত্তিক)
৬.১ কর্মী প্রোফাইল
•	ফিল্ড: নাম, বাবা/মা, NID, ঠিকানা, মোবাইল, ইমেইল, শিক্ষাগত (ন্যূনতম SSC/সমমান), যোগ্যতা, ছবি, জয়নিং তারিখ, গ্রেড/পদবি।
•	ডকস: NID/Police verification, রেফারেন্স, PVS ID (যেখানে প্রযোজ্য)।
•	যাচাইকরণ ধাপ: ডক চেকলিস্ট/অ্যাপ্রুভাল
৬.২ গ্রেড/দায়িত্ব
•	গ্রেড ১–৬ ও দায়িত্বভিত্তিক KPI
•	মার্কেটিং অফিসার (গ্রেড-৬): লিড→শেয়ার সেলস→কালেকশন (সেলস KPI)
৬.৩ উপস্থিতি/শিফট/বেতন
•	Biometric/Web check-in; Late/Overtime নিয়ম
•	Salary = Base + Allowance + Commission (সেলস)
•	Leave Policy (Casual/Sick/Earn)
•	পারফরম্যান্স রিভিউ ও ইনসেনটিভ
________________________________________
৭) Sales & Marketing (শেয়ার বিক্রয়—কর্মীদের মাধ্যমে)
৭.১ লিড/CRM
•	Lead ফিল্ড: নাম, মোবাইল, উৎস, বাজেট, আগ্রহ, স্ট্যাটাস (Lead/Prospect/Investor)
•	অটো রিমাইন্ডার/ফলোআপ নোট
৭.২ শেয়ার অফার/সেলস
•	শেয়ার প্যাকেজ—Unit Price/Qty/Payment Mode
•	শেয়ার সার্টিফিকেট (PDF, QR) অটো-জেনারেট
•	কমিশন ক্যালকুলেশন (Configurable)
•	সেলস Approval (মাল্টি-স্টেপ) – বড় বরাদ্দে ৩-Approver নীতির সাথে লিঙ্ক
৭.৩ রিপোর্ট
•	Officer-wise Sales/Collection
•	Conversion Rate
•	Commission Payable
________________________________________
৮) Investment & Share Management (ফাইলভিত্তিক আর্থিক নীতি)
৮.১ Investor Registration
•	ফিল্ড: নাম, NID, মোবাইল, ইমেইল, ঠিকানা, ব্যাংক তথ্য, Nominee
•	ভেরিফিকেশন: OTP + Admin Approval
•	Investor Dashboard: Holding, Payments, Dividends
৮.২ Share Issue/Allocation
•	শেয়ার ইউনিট মূল্য (ডিফল্ট ২৫,০০০), Qty, Issue Date
•	Payment Plan: এককালীন/কিস্তি
•	Share Ledger/Certificate/Transfer
৮.৩ ধাপভিত্তিক আর্থিক পরিকল্পনা (MÖW)
•	পর্যায়ভিত্তিক (MÖW-5, MÖW-4, ইত্যাদি) মূলধন/লাভ যোগের নিয়ম—সিস্টেমে কনফিগারেবল স্টেজ টেবিল থাকবে; লাভ/টপ-আপ যুক্ত হলে নতুন ধাপে বর্ধিত মূলধন অটো হিসাব হবে।
•	লাভের ৫০% পুনঃবিনিয়োগ—পরবর্তী ছয় মাস/ধাপে Auto-Apply (On/Off)।
৮.৪ Profit Distribution (Dividend)
•	Periodic (Monthly/Quarterly/Yearly)
•	Share-অনুপাতিক লাভ বণ্টন
•	Tax/Charges Auto-Deduct
•	Disbursement: Bank/MFS; Advice/Slip জেনারেট
•	Dividend History (Investor-wise)
________________________________________
৯) Accounts & Finance (ফাইলভিত্তিক সংখ্যা/ধাপের প্রতিফলন)
৯.১ আয়/ব্যয়/কিস্তি
•	Daily Cash/Bank Book
•	Installment Schedule + Reminder
•	Voucher: Receipt/Payment/Journal
৯.২ আর্থিক ধাপ/সংখ্যা (রেফারেন্স)
•	ফাইলে প্রদত্ত মূলধন/বোনাস/ধাপভিত্তিক বৃদ্ধি ও মোট আয়-ব্যয়ের সারাংশ—এই প্যারামিটারগুলো কনফিগ থাকবে; রিপোর্টে ধাপ-ট্র্যাকিং দেখা যাবে।
•	৩য় ধাপ/৪র্থ ধাপ ইত্যাদির Target vs Achieved রিপোর্ট (স্টেজ নাম/তারিখসহ)
৯.৩ রিপোর্ট
•	Trial Balance, Income Statement, Balance Sheet
•	Cashflow, Bank Reconciliation
•	Audit Log (Entity/Action/Time/IP)
________________________________________
১০) ফর্ম-ফিল্ড/ভ্যালিডেশন (ডেভেলপমেন্ট-রেডি)
১০.১ Customer Form
•	Required: নাম, মোবাইল, NID, ঠিকানা; Optional: ইমেইল, রেফারেন্স
•	NID format/file upload বাধ্যতামূলক; Duplicate NID Block
•	Status: New→Verified (ডক চেক)
১০.২ Employee Form
•	Required: নাম, মোবাইল, NID, ঠিকানা, শিক্ষাগত (SSC/সমমান), গ্রেড; Docs: NID/Police Verification/Photo; PVS ID (যদি প্রযোজ্য)
১০.৩ Investor Form
•	Required: নাম, NID, মোবাইল, ইমেইল, ঠিকানা; Bank/Nominee
•	OTP Verify + Admin Approve
১০.৪ Share Issue Form
•	Fields: Investor, Unit Price (ডিফল্ট ২৫,০০০), Qty, Payment Mode (One-time/Installment), Stage (MÖW), Approver(s)
•	Validation: Amount = Unit×Qty; Large Issue → 3-Approver Gate
১০.৫ Booking Form
•	Date In/Out, Guest Count, Room Type, Price Plan, Payment
________________________________________
১১) Workflow (ধাপে ধাপে)
১১.১ শেয়ার সেলস
1.	Officer লিড এন্ট্রি করবে → Prospect
2.	Proposal/Brochure পাঠাবে
3.	Investor Register → Verify
4.	Share Issue (Qty/Price) → Approval Flow (3-Approver when flagged)
5.	Payment (One-time/Installment)
6.	Certificate + Receipt
7.	Commission Auto-Calc
১১.২ ধাপভিত্তিক (MÖW) ফান্ড গ্রোথ
1.	Stage Config (MÖW-5/4/6...)
2.	Period Close → Profit Add → 50% Reinvest Enable থাকলে Auto-Topup
3.	Next Stage Capital Compute
4.	Reports Update
১১.৩ বুকিং
1.	Search → Select Room → Pay → Confirm
2.	Invoice + SMS/Email
3.	Cancel/Refund (Policy)
________________________________________
১২) রিপোর্ট/ড্যাশবোর্ড (Must-have)
•	Admin: Approvals pending, Policy changes, Audit
•	Director/MD: Stage-wise Capital, Profit, Sales, Booking
•	Sales Manager: Officer-wise Sales/Commission
•	HR: Attendance, Salary, Grade Movement
•	Accounts: TB/IS/BS, Cashflow, Reco
•	Investor: Holdings, Payments, Dividends
•	Customer: Bookings, Invoices
________________________________________
১৩) Non-Functional
•	Security: RBAC, Encryption, SSL, 2FA
•	Performance: ১০০০+ concurrent users
•	Availability: 99.9% target
•	Backup: Daily + On-demand
•	Localization: বাংলা/ইংরেজি
•	Logs: ১ বছর অনলাইন, আর্কাইভ ৫ বছর
________________________________________
১৪) টেক স্ট্যাক (প্রস্তাবিত)
•	Frontend: React/Tailwind
•	Backend: Laravel/Django REST
•	DB: MySQL/PostgreSQL
•	Auth: JWT/OAuth2
•	Reports: PDF/Excel Export
________________________________________
১৫) Accept/Done Criteria (QA Checklist)
•	3-Approver flow কার্যকর (flagged ops)
•	Customer/Employee NID বাধ্যতামূলক—ডুপ্লিকেট ব্লক
•	MÖW stage config + 50% reinvest অটো কাজ করে
•	Share certificate/receipt তৈরি হয়
•	Booking → Pay → Confirm → Cancel/Refund কাজ করে
•	All role-wise permission matrix pass
________________________________________
১৬) ট্রেসেবিলিটি টেবিল (Requirement → Screen/Process)
Req ID	উৎস/ফাইল	স্ক্রিন/প্রসেস
R-ADM-01	৩-Approver পলিসি	Approval Settings, Issue Approvals
R-CUS-02	Customer NID বাধ্যতামূলক	Customer Form + Verify
R-EMP-03	Employee SSC/Docs	Employee Form + Doc Checklist
R-INV-04	Unit=25,000 (config)	Share Config + Issue
R-MOW-05	ধাপ/লাভ-টপআপ/৫০% reinvest	Stage Engine + Close Period

