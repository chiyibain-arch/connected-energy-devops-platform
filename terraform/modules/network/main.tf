
# ============================================================
# CONNECTED ENERGY PLATFORM - AZURE NETWORK INFRASTRUCTURE
# ============================================================

# 1. Virtual Network
# Main private network for our Azure infrastructure.
resource "azurerm_virtual_network" "main" {
  name                = "vnet-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  address_space       = var.vnet_address_space

  tags = var.tags
}

# 2. AKS Subnet
# Dedicated network segment for Kubernetes infrastructure.
resource "azurerm_subnet" "aks" {
  name                 = "snet-aks-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.aks_subnet_address_prefixes

  lifecycle {
    replace_triggered_by = [azurerm_virtual_network.main.id]
  }
}

# 3. Private Endpoint Subnet
# Reserved for private connectivity to supported Azure services.
resource "azurerm_subnet" "private_endpoints" {
  name                 = "snet-private-endpoints-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.private_endpoint_subnet_address_prefixes

  lifecycle {
    replace_triggered_by = [azurerm_virtual_network.main.id]
  }
}

# 4. Self-Hosted Runner Subnet
# Dedicated network for GitHub Actions build infrastructure.
resource "azurerm_subnet" "runners" {
  name                 = "snet-runners-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.10.17.0/24"]
  # Disable implicit Internet egress; configure explicit NAT/Firewall before VM deployment.
  default_outbound_access_enabled = false

  lifecycle {
    replace_triggered_by = [azurerm_virtual_network.main.id]
  }
}

# 5. AKS Network Security Group
# Network security boundary for the AKS subnet.
resource "azurerm_network_security_group" "aks" {
  name                = "nsg-aks-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  tags = var.tags
}

# 6. Associate AKS Subnet with its NSG
resource "azurerm_subnet_network_security_group_association" "aks" {
  subnet_id                 = azurerm_subnet.aks.id
  network_security_group_id = azurerm_network_security_group.aks.id
}

# 7. Runner Network Security Group
# Dedicated NSG for self-hosted GitHub Actions runners.
resource "azurerm_network_security_group" "runners" {
  name                = "nsg-runners-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  tags = var.tags
}

# 8. Deny direct Internet access to runner VMs.
# NSGs are stateful: return traffic for permitted connections
# does not require a separate inbound allow rule.
resource "azurerm_network_security_rule" "runners_deny_internet_inbound" {
  name                        = "Deny-Internet-Inbound"
  priority                    = 100
  direction                   = "Inbound"
  access                      = "Deny"
  protocol                    = "*"
  source_port_range           = "*"
  destination_port_range      = "*"
  source_address_prefix       = "Internet"
  destination_address_prefix  = "*"
  resource_group_name         = var.resource_group_name
  network_security_group_name = azurerm_network_security_group.runners.name
}

# 9. Associate Runner Subnet with Runner NSG
resource "azurerm_subnet_network_security_group_association" "runners" {
  subnet_id                 = azurerm_subnet.runners.id
  network_security_group_id = azurerm_network_security_group.runners.id
}
