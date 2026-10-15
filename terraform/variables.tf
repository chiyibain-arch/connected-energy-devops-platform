variable "environment" {
  description = "Deployment environment such as dev, stage, or prod"
  type        = string
  default     = "dev"
}
variable "location" {
  description = "Azure region where environment resources will be deployed"
  type        = string
  default     = "eastus"
}
variable "project_name" {
  description = "Base name used to identify resources for the connected energy platform"
  type        = string
  default     = "connected-energy"
}
variable "vnet_address_space" {
  description = "CIDR address space assigned to the environment Virtual Network"
  type        = list(string)
}
variable "aks_subnet_address_prefixes" {
  description = "CIDR address prefixes assigned to the AKS subnet"
  type        = list(string)
}
variable "private_endpoint_subnet_address_prefixes" {
  description = "CIDR address prefixes assigned to the private endpoint subnet"
  type        = list(string)
}
variable "acr_sku" {
  description = "Azure Container Registry service tier"
  type        = string
  default     = "Basic"
}
variable "log_retention_in_days" {
  description = "Number of days Log Analytics data is retained"
  type        = number
  default     = 30
}
variable "aks_node_count" {
  description = "Number of nodes in the default AKS node pool"
  type        = number
}
variable "aks_vm_size" {
  description = "VM size used by the default AKS node pool"
  type        = string
}
variable "tags" {
  description = "Common governance and ownership tags applied to Azure resources"
  type        = map(string)
}

# Public SSH key supplied securely at plan/apply time (never a private key).
variable "runner_ssh_public_key" {
  description = "OpenSSH public key for the GitHub runner VM administrator"
  type        = string
  sensitive   = true
}
