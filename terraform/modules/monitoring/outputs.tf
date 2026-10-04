output "log_analytics_workspace_id" {
  description = "Resource ID of the Log Analytics Workspace, used by AKS Container Insights"
  value       = azurerm_log_analytics_workspace.main.id
}

output "log_analytics_workspace_name" {
  description = "Name of the Log Analytics Workspace"
  value       = azurerm_log_analytics_workspace.main.name
}
