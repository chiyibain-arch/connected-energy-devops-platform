resource "azurerm_resource_group" "main" {
  name     = "rg-${var.project_name}-${var.environment}"
  location = var.location

  tags = var.tags
}

module "network" {
  source = "./modules/network"

  environment         = var.environment
  project_name        = var.project_name
  location            = var.location
  resource_group_name = azurerm_resource_group.main.name

  vnet_address_space                       = var.vnet_address_space
  aks_subnet_address_prefixes              = var.aks_subnet_address_prefixes
  private_endpoint_subnet_address_prefixes = var.private_endpoint_subnet_address_prefixes

  tags = var.tags
}

module "acr" {
  source = "./modules/acr"

  project_name        = var.project_name
  environment         = var.environment
  location            = var.location
  resource_group_name = azurerm_resource_group.main.name
  acr_sku             = var.acr_sku

  tags = var.tags
}

module "monitoring" {
  source = "./modules/monitoring"

  environment           = var.environment
  project_name          = var.project_name
  location              = var.location
  resource_group_name   = azurerm_resource_group.main.name
  log_retention_in_days = var.log_retention_in_days

  tags = var.tags
}

module "aks" {
  source = "./modules/aks"

  environment         = var.environment
  project_name        = var.project_name
  location            = var.location
  resource_group_name = azurerm_resource_group.main.name

  aks_subnet_id              = module.network.aks_subnet_id
  log_analytics_workspace_id = module.monitoring.log_analytics_workspace_id

  node_count = var.aks_node_count
  vm_size    = var.aks_vm_size

  tags = var.tags
}

# Grants the AKS kubelet managed identity least-privilege (AcrPull) access to the
# registry via Azure RBAC. ACR admin credentials remain disabled.
resource "azurerm_role_assignment" "aks_acr_pull" {
  scope                            = module.acr.acr_id
  role_definition_name             = "AcrPull"
  principal_id                     = module.aks.kubelet_identity_object_id
  skip_service_principal_aad_check = true
}

# Dedicated GitHub Actions runner compute, isolated from AKS nodes.
# This module creates the VM; agent registration is configured separately.
module "github_runner" {
  count  = var.enable_github_runner ? 1 : 0
  source = "./modules/github-runner"

  project_name        = var.project_name
  environment         = var.environment
  location            = var.location
  resource_group_name = azurerm_resource_group.main.name
  subnet_id           = module.network.runner_subnet_id
  ssh_public_key      = var.runner_ssh_public_key
  tags                = var.tags
}
