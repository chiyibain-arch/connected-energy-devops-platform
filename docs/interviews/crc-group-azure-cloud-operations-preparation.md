# CRC Group — Azure Cloud Operations Engineer: Project Alignment

Interview: October 21, 2026 at 12:00 PM ET, Microsoft Teams
Interviewer: Jason Nance
Recruiter: Michelle Pavese (Apex Systems)
Project: Connected Energy DevOps Platform

This is a planned implementation roadmap for a fictional connected-energy company, not a claim that CRC Group systems were accessed or that unimplemented features are deployed.

## Phase 1 — Inventory and validate existing platform
Verify Laravel/MySQL/MQTT application, Docker workflow, Terraform modules, current Azure resources and Git branch. Capture terraform fmt, validate and plan output and deployment evidence. Interview connection: operational discovery before changes.

## Phase 2 — Reusable Terraform and Azure networking
Extend reusable modules for resource groups, VNets, subnets, NSGs, private endpoints, managed identity, Key Vault, VMs, Azure SQL, monitoring and recovery. Use environment separation, remote Azure Storage state and locking, RBAC, tags, diagnostic settings and PR-reviewed plans. Interview connection: Terraform modules, drift, state and change controls.

## Phase 3 — Azure compute and Windows Server operations
Design VM provisioning, managed disks, Azure Monitor Agent, boot diagnostics, patching, NSG restrictions and event-log troubleshooting. Write a runbook for failed boot, unavailable services, disk pressure and network issues. Interview connection: incident troubleshooting and Windows Server administration.

## Phase 4 — SQL Server IaaS and Azure SQL PaaS
IaaS: SQL Server on an Azure VM, including OS/instance maintenance, disk sizing, network connectivity, backups and performance metrics.
PaaS: Azure SQL Database with private endpoint, Entra authentication, auditing, managed backups and point-in-time restore.
Plan a small sample schema, data migration and validation exercise. The current app uses MySQL; do not claim a completed SQL Server migration. Interview connection: IaaS vs PaaS operational responsibility, connectivity, backup and SQL troubleshooting.

## Phase 5 — Azure DevOps CI/CD
Plan PR validation, tests, secret scanning, SAST and IaC checks, Terraform plan artifact, environment approvals, apply and smoke tests. Prefer workload identity federation over long-lived secrets. Interview connection: secure, repeatable infrastructure releases.

## Phase 6 — Monitoring, PagerDuty and operations
Integrate Azure Monitor, Log Analytics, diagnostic settings and alerts for availability, error rate, VM CPU/disk, SQL utilization and failed backups. Write severity-based escalation and post-incident review runbooks. Interview connection: describe a realistic after-hours incident and root-cause analysis.

## Phase 7 — Backup and disaster recovery
Plan VM enrollment in Azure Backup/Backup Center, Azure SQL PITR, retention and isolated restore testing. Evaluate Azure Site Recovery for supported VM workloads, failover/failback, and documented RPO/RTO. Interview connection: explain why backup, restore and disaster recovery are different.

## Phase 8 — Azure Cost Management
Review subscription/resource-group/tag cost, VM SKUs, disk tiers, idle capacity and SQL compute options. Compare cost savings against availability and performance requirements. Interview connection: show evidence-based right-sizing decisions.

## Interview questions for Jason Nance
1. Explain Terraform reusable modules, state locking and drift.
2. Troubleshoot a Windows VM that cannot connect to Azure SQL.
3. Compare SQL Server on Azure VM vs Azure SQL Database.
4. Walk through a secure client data migration.
5. Design an Azure DevOps infrastructure deployment pipeline.
6. Respond to a failed backup or PagerDuty incident.
7. Explain Azure Site Recovery and restore testing.
8. Identify and safely reduce Azure costs.

## Evidence and status
- Existing app and Azure inventory: verify current state.
- Terraform expansion: planned; confirm deployed modules individually.
- SQL IaaS/PaaS: planned, not verified as deployed.
- Azure DevOps pipeline: planned, not verified as run.
- Backup/restore, Site Recovery and cost report: planned, not verified as tested.

Only present demonstrably implemented work as completed in interviews.
