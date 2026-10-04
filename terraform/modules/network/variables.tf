variable "environment" {
  description = "Deployment environment such as dev, stage, or prod"
  type        = string
}

variable "project_name" {
  description = "Base project name used for Azure network resource naming"
  type        = string
}

variable "location" {
  description = "Azure region where network resources are deployed"
  type        = string
}

variable "resource_group_name" {
  description = "Resource group containing the network resources"
  type        = string
}

variable "vnet_address_space" {
  description = "CIDR address space assigned to the Azure Virtual Network"
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

variable "tags" {
  description = "Common governance and ownership tags applied to Azure resources"
  type        = map(string)
}
