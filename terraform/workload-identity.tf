
resource "azurerm_user_assigned_identity" "app_workload" {
  name                = "id-${var.project_name}-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name

  tags = var.tags
}

resource "azurerm_federated_identity_credential" "aks_app" {
  name                = "fic-${var.project_name}-${var.environment}"
  resource_group_name = azurerm_resource_group.main.name
  parent_id           = azurerm_user_assigned_identity.app_workload.id

  issuer   = module.aks.oidc_issuer_url
  subject  = "system:serviceaccount:connected-energy-dev:connected-energy-sa"
  audience = ["api://AzureADTokenExchange"]
}
