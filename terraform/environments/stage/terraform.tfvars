environment  = "stage"
location     = "eastus"
project_name = "connected-energy"

vnet_address_space = ["10.20.0.0/16"]

aks_subnet_address_prefixes = [
  "10.20.0.0/20"
]

private_endpoint_subnet_address_prefixes = [
  "10.20.16.0/24"
]

acr_sku               = "Basic"
log_retention_in_days = 30

aks_node_count = 2
aks_vm_size    = "Standard_D2s_v3"

tags = {
  Environment = "Stage"
  Application = "ConnectedEnergy"
  ManagedBy   = "Terraform"
  Owner       = "PlatformEngineering"
}
