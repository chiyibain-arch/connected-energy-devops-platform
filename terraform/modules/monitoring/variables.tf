variable "environment" {
  description = "Deployment environment such as dev, stage, or prod"
  type        = string
}

variable "project_name" {
  description = "Base project name used for Log Analytics resource naming"
  type        = string
}

variable "location" {
  description = "Azure region where the Log Analytics Workspace is deployed"
  type        = string
}

variable "resource_group_name" {
  description = "Resource group containing the Log Analytics Workspace"
  type        = string
}

variable "log_retention_in_days" {
  description = "Number of days Log Analytics data is retained"
  type        = number
  default     = 30

  validation {
    condition     = var.log_retention_in_days >= 30 && var.log_retention_in_days <= 730
    error_message = "Log retention must be between 30 and 730 days."
  }
}

variable "tags" {
  description = "Common governance and ownership tags applied to Azure resources"
  type        = map(string)
}
