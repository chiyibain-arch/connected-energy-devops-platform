output "cluster_id" {
  description = "Resource ID of the AKS cluster"
  value       = azurerm_kubernetes_cluster.main.id
}

output "cluster_name" {
  description = "Name of the AKS cluster"
  value       = azurerm_kubernetes_cluster.main.name
}

output "node_resource_group" {
  description = "Name of the auto-generated resource group holding AKS node infrastructure"
  value       = azurerm_kubernetes_cluster.main.node_resource_group
}

output "cluster_identity_principal_id" {
  description = "Principal (object) ID of the AKS cluster's system-assigned managed identity"
  value       = azurerm_kubernetes_cluster.main.identity[0].principal_id
}

output "kubelet_identity_object_id" {
  description = "Object ID of the AKS kubelet identity, used to grant ACR pull access via RBAC"
  value       = azurerm_kubernetes_cluster.main.kubelet_identity[0].object_id
}

output "oidc_issuer_url" {
  description = "OIDC issuer URL for AKS Workload Identity federation"
  value       = azurerm_kubernetes_cluster.main.oidc_issuer_url
}
