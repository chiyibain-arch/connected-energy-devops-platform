environment  = "prod"
location     = "eastus"
project_name = "connected-energy"

vnet_address_space = ["10.30.0.0/16"]

aks_subnet_address_prefixes = [
  "10.30.0.0/20"
]

private_endpoint_subnet_address_prefixes = [
  "10.30.16.0/24"
]

acr_sku               = "Standard"
log_retention_in_days = 90

aks_node_count = 3
aks_vm_size    = "Standard_D4s_v3"

tags = {
  Environment = "Prod"
  Application = "ConnectedEnergy"
  ManagedBy   = "Terraform"
  Owner       = "PlatformEngineering"
}
