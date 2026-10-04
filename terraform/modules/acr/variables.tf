variable "project_name" {
  description = "Base project name used for ACR resource naming"
  type        = string
}

variable "environment" {
  description = "Deployment environment such as dev, stage, or prod"
  type        = string
}

variable "resource_group_name" {
  description = "Resource group where ACR will be deployed"
  type        = string
}

variable "location" {
  description = "Azure region where ACR will be deployed"
  type        = string
}

variable "acr_sku" {
  description = "Azure Container Registry service tier"
  type        = string
  default     = "Basic"

  validation {
    condition     = contains(["Basic", "Standard", "Premium"], var.acr_sku)
    error_message = "ACR SKU must be Basic, Standard, or Premium."
  }
}

variable "tags" {
  description = "Common governance and ownership tags applied to ACR"
  type        = map(string)
}
