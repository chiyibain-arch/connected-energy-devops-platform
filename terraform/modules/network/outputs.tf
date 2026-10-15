
# ============================================================
# CONNECTED ENERGY PLATFORM - NETWORK MODULE OUTPUTS
# ============================================================

# 1. Azure Virtual Network ID
output "vnet_id" {
  description = "ID of the Azure Virtual Network"
  value       = azurerm_virtual_network.main.id
}

# 2. Azure Virtual Network Name
output "vnet_name" {
  description = "Name of the Azure Virtual Network"
  value       = azurerm_virtual_network.main.name
}

# 3. AKS Subnet ID
output "aks_subnet_id" {
  description = "ID of the subnet used by AKS"
  value       = azurerm_subnet.aks.id
}

# 4. Private Endpoint Subnet ID
output "private_endpoint_subnet_id" {
  description = "ID of the subnet used by private endpoints"
  value       = azurerm_subnet.private_endpoints.id
}

# 5. AKS Network Security Group ID
output "aks_nsg_id" {
  description = "ID of the AKS Network Security Group"
  value       = azurerm_network_security_group.aks.id
}

# 6. Self-Hosted Runner Subnet ID
output "runner_subnet_id" {
  description = "ID of the dedicated subnet for GitHub Actions self-hosted runners"
  value       = azurerm_subnet.runners.id
}

# 7. Self-Hosted Runner Network Security Group ID
output "runner_nsg_id" {
  description = "ID of the Network Security Group protecting self-hosted runners"
  value       = azurerm_network_security_group.runners.id
}

