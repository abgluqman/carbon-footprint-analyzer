<?php
// If someone tries to browse the reports folder directly,
// send them back to the dashboard
header("Location: ../pages/dashboard.php");
exit();
?>
```

**What this does:**
- If someone types `http://yoursite.com/reports/` in their browser
- They get redirected to the dashboard instead of seeing a file list

---

## **Why Is This Important?**

### **Without Protection:**
```
Someone could access:
http://yoursite.com/reports/
└── See all PDF files:
    - carbon_report_1_20250211.pdf
    - carbon_report_2_20250211.pdf
    - carbon_report_3_20250211.pdf
    └── Download anyone's report! ❌ SECURITY RISK!
```

### **With Protection:**
```
Someone tries:
http://yoursite.com/reports/
└── Gets redirected to dashboard ✅

PDFs can only be accessed through:
report.php?id=123&download=1
└── PHP verifies user owns this report ✅
└── Then allows download ✅