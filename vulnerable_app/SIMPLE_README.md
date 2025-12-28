# Simple SCADA System - Student Project

## New Files (Simple Version)

### 1. `includes/scada_db.php`
- Basic database for SCADA state
- Stores: water_level, pump_status, valve_position
- Simple functions: `get_scada()` and `update_scada()`

### 2. `controls.php`
- Control panel page
- Toggle pump ON/OFF
- Adjust valve position (0-100%)
- **Vulnerable:** No permission check - any logged user can control!

### 3. `dashboard_live.php`
- Live dashboard with real SCADA data
- Shows current water level, valve, pump status
- Same SQL injection vulnerability as original dashboard.php

## Quick Start

```bash
# 1. Login to the system
http://localhost/vulnerable_app/index.php

# 2. View live dashboard
http://localhost/vulnerable_app/dashboard_live.php

# 3. Control SCADA system
http://localhost/vulnerable_app/controls.php
```

## What Each File Does

**scada_db.php**
- Creates scada_state table
- Default values: water=1240m³, pump=OFF, valve=50%

**controls.php**
- Simple control interface
- 2 controls: Pump toggle, Valve slider
- Logs all actions

**dashboard_live.php**
- Shows live data from database
- Has SQL injection (same blacklist as original)
- Link to controls page

## Vulnerabilities

1. **SQL Injection** - dashboard_live.php (case-sensitive blacklist)
2. **Authorization Bypass** - controls.php (no permission check)
3. **LDAP Injection** - index.php (from original)
4. **LDAP Injection #2** - search.php (from original)
5. **No Rate Limiting** - index.php (from original)

## Usage

Just login and use the controls. That's it!

Much simpler than the previous version.
