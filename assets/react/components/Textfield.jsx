import React from "react";

const Textfield = ({
                       label,
                       caption,
                       isError = false,
                       errorCaption,
                       iconLeft,
                       iconRight,
                       disabled = false,
                       placeholder = "",
                       maxLength,
                       value,
                       onChange,
                       size = "md",
                       className = "",
                       autoComplete = "off",
                       ...props
                   }) => {
    const sizeClasses = {
        sm: "text-sm h-8",
        md: "text-sm h-10",
    };

    return (
        <div className="flex flex-col w-full">
            {label && <label className="text-xs font-semibold text-secondary mb-2">{label}</label>}
            <div className={`relative flex items-center w-full`}>
                {iconLeft && <span className="absolute left-3 text-primary w-4 h-4 flex items-center justify-center">{iconLeft}</span>}
                <input
                    className={`
            w-full border rounded-sm px-3 outline-none text-sm
            ${disabled ? "text-secondary cursor-not-allowed" : "text-primary"}
            ${sizeClasses[size] || sizeClasses.md}
            ${iconLeft ? "pl-9" : ""} 
            ${iconRight ? "pr-9" : ""}
            ${isError ? "border-red-500" : "border-input-border focus:border-primary"}
            ${className}
          `}
                    disabled={disabled}
                    placeholder={placeholder}
                    maxLength={maxLength}
                    value={value}
                    onChange={onChange}
                    autoComplete={autoComplete}
                    {...props}
                />
                {iconRight && <span className="absolute right-3 text-primary w-4 h-4 flex items-center justify-center">{iconRight}</span>}
            </div>
            {caption && <p className="text-xs text-terciary mt-2">{caption}</p>}
            {isError && errorCaption && <p className="text-xs text-red-500 font-medium mt-2">{errorCaption}</p>}
        </div>
    );
};

export default Textfield;