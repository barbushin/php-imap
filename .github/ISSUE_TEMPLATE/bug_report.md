---
name: Bug report
about: Create a report to help us improve
title: "[BUG] A short description of what the bug is"
labels: needs investigation
assignees: ''

---

**Environment (please complete the following information):**
 - PHP IMAP version: [e.g. 3.0.11]
 - PHP Version: [e.g. 7.1.26]
 - Type of execution: [e.g. Daemon / CLI or Web Server]

**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior.

The used code:
```php
$mailbox = new Mailbox(...
```

The headers of the parsed email, if required and possible (only, if it's NOT confidential):
```
Received: from BN3NAM04HT142.eop-NAM04.prod.protection.outlook.com
(2603:10a6:209:2a::30) by AM6PR05MB6294.eurprd05.prod.outlook.com with HTTPS
via AM6PR07CA0017.EURPRD07.PROD.OUTLOOK.COM; Sun, 5 May 2019 12:29:42 +0000
Received: from BN3NAM04FT054.eop-NAM04.prod.protection.outlook.com
(10.152.92.54) by BN3NAM04HT142.eop-NAM04.prod.protection.outlook.com
(10.152.92.244) with Microsoft SMTP Server (version=TLS1_2,
cipher=TLS_ECDHE_RSA_WITH_AES_256_CBC_SHA384) id 15.20.1835.13; Sun, 5 May
2019 12:29:41 +0000
...
```

**Expected behavior**
A clear and concise description of what you expected to happen.

**Screenshots / Outputs**
If applicable, add screenshots or outputs of your script to help explain your problem.

**Additional context**
Add any other context about the problem here.
