environment  = "dev"
location     = "eastus"
project_name = "connected-energy"

vnet_address_space = ["10.10.0.0/16"]

aks_subnet_address_prefixes = [
  "10.10.0.0/20"
]

private_endpoint_subnet_address_prefixes = [
  "10.10.16.0/24"
]

acr_sku               = "Basic"
log_retention_in_days = 30

aks_node_count = 1
aks_vm_size    = "Standard_B2s"

tags = {
  Environment = "Dev"
  Application = "ConnectedEnergy"
  ManagedBy   = "Terraform"
  Owner       = "PlatformEngineering"
}
