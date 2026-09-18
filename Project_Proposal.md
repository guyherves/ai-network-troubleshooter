# Project Proposal: NetSentry

## A Web-Based Network Monitoring, Asset Management, and Intrusion Prevention System

## 1. Abstract

Small organizations, schools, and computer laboratories depend on reliable local networks, but they often lack an affordable tool that combines network visibility, device inventory, service diagnostics, and security event management. Network administrators may need to use several disconnected utilities to discover devices, check availability, investigate slow connections, identify suspicious activity, and prepare reports.

This project proposes **NetSentry**, a web-based Network Operations Center (NOC) and rule-based Intrusion Prevention System (IPS). The application discovers devices on a local subnet, stores network asset information, performs ICMP availability and latency checks, measures traffic statistics, evaluates network conditions using configurable rules, detects suspicious connection patterns, and presents the results through an administrative dashboard. It also provides alert management, firewall rule administration, role-based access control, network topology visualization, device history, and CSV/PDF reporting.

NetSentry is implemented as a PHP application running on an AMPPS web server, with MySQL for persistent storage and Bootstrap, JavaScript, and Chart.js for the user interface. The system is intended for controlled local-network environments and is designed to support faster diagnosis, better asset visibility, and more consistent security response.

## 2. Background

Network availability and security are closely connected. An offline router, an unknown device, excessive latency, or a sudden increase in active connections can affect productivity and may also indicate a security incident. In many small network environments, these conditions are identified manually through operating-system commands and are not retained in a central history.

A centralized monitoring application can reduce this difficulty by collecting network information, applying repeatable rules, and presenting operational information in one place. The value of such a system is not only the detection of threats, but also the ability to explain the state of the network and provide evidence for troubleshooting and auditing.

## 3. Problem Statement

Network administrators in small and medium-sized environments face the following problems:

1. Network devices are not always documented in a current, searchable inventory.
2. Device outages, packet loss, and high latency may be discovered only after users report a problem.
3. Port scanning and suspicious connection activity can be difficult to identify using manual inspection.
4. Monitoring data, alerts, device notes, and security events are often stored in separate tools or are not stored at all.
5. Preparing reliable network availability and security reports requires manual collection and formatting.
6. Direct firewall changes can be risky when there is no controlled dry-run mode or audit history.

NetSentry addresses these problems by providing one authenticated web interface for monitoring, diagnosis, asset management, security events, and reporting.

## 4. Aim of the Project

To design and develop a web-based network monitoring and intrusion prevention system that provides centralized LAN visibility, rule-based diagnosis, security event detection, and controlled response for small and medium-sized network environments.

## 5. Objectives

### 5.1 Main Objective

To develop NetSentry as a practical and centralized NOC/IPS platform for monitoring network availability, managing connected devices, detecting suspicious activity, and supporting administrator response.

### 5.2 Specific Objectives

The project will:

1. Discover devices on a local subnet using ARP information and record their IP address, MAC address, hostname, type, and status.
2. Monitor device availability using ICMP ping checks and record latency, packet loss, status changes, and last-seen information.
3. Measure network traffic and bandwidth indicators using operating-system network statistics and store historical snapshots.
4. Implement deterministic rules for conditions such as device outage, high latency, packet loss, excessive bandwidth, port scanning, and suspicious ports.
5. Provide an interactive dashboard with key performance indicators, charts, alerts, device summaries, and topology visualization.
6. Provide a port-scanning utility for checking common TCP services on authorized target devices.
7. Detect suspicious active connections and record security events with severity, confidence, description, and action taken.
8. Provide controlled firewall rule management, including a dry-run option and support for host firewall commands where enabled by the administrator.
9. Implement role-based access control for Admin, Analyst, and Viewer users.
10. Maintain searchable history for diagnostics, alerts, device changes, and security events.
11. Generate network inventory, availability, change-history, CSV, and printable PDF reports.
12. Evaluate the system using functional, security, usability, and performance tests in a controlled local network.

## 6. Research Questions

1. How can a centralized web application improve visibility of devices and availability in a small local network?
2. How effectively can deterministic rules identify common network problems and suspicious connection patterns?
3. How can role-based access and firewall dry-run controls reduce the risk of unauthorized administrative actions?
4. Does centralized history and report generation reduce the effort required for network troubleshooting and auditing?

## 7. Proposed System

NetSentry combines network monitoring, asset management, diagnostics, and security response in one system.

### 7.1 Main System Modules

#### A. Authentication and User Management

- Secure login and logout.
- Password hashing using PHP password functions.
- User registration and profile management.
- Admin-managed user accounts.
- Admin, Analyst, and Viewer roles with different permissions.

#### B. Network Discovery and Inventory

- Local subnet and gateway detection.
- ARP-based discovery of reachable LAN devices.
- Device records containing IP address, MAC address, hostname, device type, vendor, operating system, and status.
- Trusted-device marking and administrator notes.
- Filters for online, offline, unknown, trusted, and recently discovered devices.

#### C. Availability and Telemetry Monitoring

- Individual and batch device ping checks.
- Latency and packet-loss measurement.
- Device status transition detection, including offline and reconnected events.
- Background monitoring support through a PHP CLI script and scheduled execution.
- Bandwidth, protocol, packet, and dropped-traffic statistics.

#### D. Rule-Based Diagnostics

The diagnostic engine applies predefined thresholds to collected telemetry. Example classifications include:

- Normal network condition.
- Device offline or unreachable.
- High latency or packet loss.
- High bandwidth usage.
- Excessive active connections.
- Port scanning activity.
- Suspicious service-port activity.

For each applicable condition, the system records the result and provides a recommendation for administrator action.

#### E. Intrusion Detection and Firewall Management

- Inspection of active network connections using operating-system tools.
- Detection of suspicious ports and unusual connection patterns.
- Identification of port scans based on the number of distinct ports accessed.
- Identification of traffic floods based on active connection thresholds.
- Severity classification and security-event history.
- Manual firewall block and unblock actions for authorized users.
- Firewall dry-run mode to simulate response without changing the operating-system firewall.
- Support for Windows `netsh advfirewall` and Linux `iptables` when real enforcement is enabled.

#### F. Dashboard, Alerts, and Visualization

- NOC dashboard showing online/offline devices, active alerts, latency, bandwidth, and uptime.
- Real-time or near-real-time updates using API polling and available WebSocket integration.
- Alert acknowledgement, resolution, and history management.
- Interactive network topology showing the WAN, gateway, and connected hosts.
- Chart-based views of latency, packet loss, protocols, device types, and system health.

#### G. Reporting and Audit History

- Network inventory reports.
- Availability and latency reports.
- Network change and event reports.
- CSV export for analysis in spreadsheet software.
- Printable PDF-ready audit reports.
- Persistent records for devices, pings, alerts, diagnoses, scans, traffic snapshots, and security events.

## 8. System Workflow

1. The user signs in through the authentication module.
2. NetSentry identifies the local interface, gateway, and subnet.
3. An authorized user runs a subnet discovery scan, or a scheduled process updates monitored devices.
4. Discovered devices are stored in the MySQL database and displayed in the inventory.
5. Monitoring checks update device status, latency, packet loss, bandwidth, and traffic statistics.
6. The rule engine evaluates telemetry and active connections against configured thresholds.
7. The system records alerts, diagnostics, and security events when a condition is detected.
8. Authorized administrators review the event and may acknowledge the alert or apply a firewall rule.
9. Reports can be generated from the stored inventory, availability, and event history.

## 9. Scope of the Project

### 9.1 In Scope

- Local IPv4 subnet discovery in a controlled network.
- LAN device inventory and classification.
- ICMP-based availability and latency monitoring.
- Bandwidth and protocol statistics available from the host operating system.
- Rule-based network diagnosis.
- Common TCP port checks for authorized targets.
- Detection of selected suspicious connection and port patterns.
- Alert, event, and diagnosis history.
- Firewall rule administration with dry-run protection.
- Role-based access control.
- Dashboard visualization, topology, and reporting.
- Deployment on AMPPS with PHP and MySQL.

### 9.2 Out of Scope

- Enterprise-scale distributed monitoring across multiple geographic sites.
- Guaranteed detection of every type of malware, exploit, or encrypted attack.
- Full deep-packet inspection of encrypted traffic.
- Automatic remediation of third-party routers, switches, or cloud firewalls.
- Replacement of a commercial SIEM, endpoint detection platform, or enterprise firewall.
- Scanning networks without authorization.

## 10. Significance of the Project

### Network Administrators

NetSentry provides a single operational view of devices, availability, traffic indicators, and security events. This can reduce the time needed to locate common faults and review network changes.

### Small Organizations and Laboratories

The system offers a low-cost monitoring option using widely available PHP, MySQL, and AMPPS components.

### Students and Researchers

The project demonstrates the integration of web development, database design, network administration, rule-based reasoning, access control, and security operations.

### Management and Auditors

Persistent history and exportable reports provide evidence of device status, network changes, and security events over time.

## 11. Methodology

The project will use an iterative system-development methodology:

1. **Requirements analysis:** Identify administrator workflows, monitored metrics, user roles, and security constraints.
2. **System design:** Define the application modules, database schema, API endpoints, role permissions, and monitoring workflow.
3. **Implementation:** Develop the PHP pages, database layer, monitoring functions, APIs, dashboard, rule engine, and reporting tools.
4. **Integration:** Connect discovery, monitoring, alerts, diagnostics, firewall management, and reporting through the shared database.
5. **Testing:** Validate normal, offline, degraded, and suspicious-network scenarios in an authorized test environment.
6. **Evaluation:** Measure detection behavior, response time, usability, data accuracy, and report completeness.
7. **Documentation:** Prepare installation instructions, user guidance, limitations, and final project documentation.

## 12. Functional Requirements

The system shall:

1. Authenticate registered users before allowing access to protected pages and APIs.
2. Enforce role-based permissions for administrative, diagnostic, and read-only actions.
3. Discover and store LAN devices from the configured local subnet.
4. Display current device status and allow authorized users to run ping checks.
5. Store telemetry and status-change history with timestamps.
6. Run port checks against a specified authorized IP address.
7. Detect configured suspicious connection patterns and create security events.
8. Create, review, acknowledge, resolve, and clear alerts according to user permissions.
9. Add, list, and remove firewall rules when the administrator enables enforcement.
10. Support dry-run firewall operation for safe testing.
11. Display dashboards, charts, topology, and device detail pages.
12. Export selected inventory and event information as CSV and printable PDF reports.

## 13. Non-Functional Requirements

- **Security:** Use password hashing, prepared SQL statements, authenticated endpoints, role checks, input validation, and output escaping.
- **Usability:** Present clear status indicators, searchable tables, understandable severity levels, and actionable recommendations.
- **Reliability:** Preserve monitoring history and continue operating when individual devices are unreachable.
- **Performance:** Return dashboard and API data quickly enough for interactive local-network use.
- **Maintainability:** Separate database access, shared network functions, API endpoints, page templates, and client-side scripts.
- **Portability:** Support Windows AMPPS deployment and provide cross-platform command handling where practical.
- **Auditability:** Record timestamps, event types, affected addresses, actions, and user-triggered changes.

## 14. Technology Stack

| Layer | Technology |
|---|---|
| Web server | AMPPS Apache/PHP environment |
| Backend | PHP |
| Database | MySQL using PDO |
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Charts and visualization | Chart.js and custom canvas topology visualization |
| Network discovery | ARP table and local subnet detection |
| Availability monitoring | ICMP ping and stored telemetry history |
| Traffic statistics | Windows `netstat` or Linux interface statistics |
| Security response | Windows `netsh advfirewall` or Linux `iptables`, subject to configuration |
| Data exchange | JSON API endpoints and browser polling/WebSocket integration |
| Reporting | CSV export and printable PDF-ready HTML reports |

## 15. Database Design Overview

The database stores the main entities required for monitoring and auditing:

- `user`: accounts, credentials, and roles.
- `lan_device`: discovered device identity, classification, trust status, and current state.
- `network_interfaces`: detected local interface and gateway information.
- `network_scans`: scan history and results summary.
- `ping_history`: device availability, latency, and packet-loss measurements.
- `network_events`: new-device, offline, recovery, and other network changes.
- `network_log`: monitored network telemetry.
- `alert`: administrator notifications and resolution status.
- `diagnosis_history`: rule classifications and recommendations.
- `security_event`: detected threats, severity, confidence, and response action.
- `traffic_snapshot` and `bandwidth_snapshot`: historical traffic counters and bandwidth measurements.

## 16. Testing and Evaluation Plan

The system will be tested using an authorized local network or a controlled laboratory environment.

| Test area | Example verification |
|---|---|
| Authentication | Valid login, invalid login, logout, password update |
| Authorization | Viewer restrictions, Analyst access, Admin-only operations |
| Discovery | New device detection, duplicate handling, invalid/broadcast filtering |
| Availability | Online device, offline device, recovery, latency and packet-loss recording |
| Diagnostics | Normal, high-latency, packet-loss, and unreachable classifications |
| Security detection | Suspicious port, multi-port scan, and high-connection scenarios |
| Firewall safety | Dry-run behavior, authorized rule creation, removal, and audit record |
| Reporting | Correct CSV fields and complete printable report data |
| Usability | Navigation, filtering, dashboard readability, and error messages |
| Performance | API response time, monitoring-cycle duration, and behavior with increasing device counts |

Evaluation results will be compared with the expected state created in each controlled test scenario. The project will report false positives, missed detections, response time, and usability observations.

## 17. Expected Deliverables

1. Working NetSentry PHP web application.
2. MySQL database schema and automatic table initialization.
3. Network discovery and device inventory module.
4. Availability, telemetry, diagnostics, and alert modules.
5. Intrusion detection and controlled firewall-management module.
6. Role-based authentication and user-management module.
7. Dashboard, topology visualization, device details, and history pages.
8. CSV and printable PDF reporting tools.
9. Installation, deployment, and user documentation.
10. Test cases, results, limitations, and final project report.

## 18. Limitations and Risk Controls

NetSentry depends on the permissions and capabilities of the host operating system. ARP discovery may only show devices visible to the local network interface, and some devices may not respond to ICMP. Traffic measurements obtained from operating-system counters are indicators rather than full packet capture. Rule-based detection depends on threshold selection and may produce false positives or fail to identify unknown attack techniques.

To reduce operational risk, firewall enforcement is configurable and dry-run mode is available. Scans and firewall actions should only be performed on networks and devices for which the administrator has authorization. The system is intended to support administrator decisions, not to replace professional network-security controls.

## 19. Proposed Work Plan

| Phase | Activities |
|---|---|
| 1 | Requirements analysis and review of existing network-monitoring tools |
| 2 | Database design, authentication, and role permissions |
| 3 | Device discovery, inventory, and network-interface detection |
| 4 | Ping monitoring, telemetry history, dashboard KPIs, and charts |
| 5 | Rule diagnostics, alerts, security-event detection, and firewall controls |
| 6 | Topology visualization, device detail pages, and reports |
| 7 | Integration testing, security testing, and usability evaluation |
| 8 | Documentation, deployment preparation, demonstration, and final report |

## 20. Conclusion

NetSentry is a practical web-based solution for improving visibility and control in small local networks. By combining device discovery, inventory management, availability monitoring, rule-based diagnosis, intrusion detection, controlled firewall response, and reporting, the system turns scattered network checks into a centralized operational workflow. Its PHP/MySQL architecture makes it accessible for AMPPS deployment, while its role controls, audit history, and firewall dry-run mode support responsible administration. The completed project will provide both a useful network-management tool and a strong demonstration of applied web development, database engineering, and cybersecurity principles.
