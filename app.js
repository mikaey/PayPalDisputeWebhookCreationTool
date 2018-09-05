$(document).ready(function() {
  $("#process").click(function() {
    var client_id = $("#client_id").val().trim();
    var secret = $("#secret").val().trim();
    var webhook_url = $("#webhook_url").val().trim();

    var errors = [];
    if(!client_id.length) {
      errors.push("Client ID is required.");
    }

    if(!secret.length) {
      errors.push("Secret is required.");
    }

    if(!webhook_url.length) {
      errors.push("Webhook URL is required.");
    }

    if(errors.length) {
      window.alert("Please fix the following errors before continuing:\n\n" + errors.join("\n"));
      return;
    }

    $("#process").text("Processing...please wait...");
    $("#process").attr("disabled", "disabled");

    var req = {
      client_id: client_id,
      secret: secret,
      webhook_url: webhook_url
    };

    $("#result,#webhook_id,#errmsg").text("(pending)");

    $.ajax({
      url: "ajax.php",
      data: req,
      method: "POST",
      dataType: "json"
    }).done(function(data, status) {
      if(data.ok) {
        $("#result").text("Success");
        if(data.id) {
          $("#webhook_id").text(data.id);
          $("#errmsg").text("");
        } else {
          $("#webhook_id").text("(Unknown)");
          $("#errmsg").text("Webhook creation was successful, but no webhook ID was present in the response");
        }
      } else {
        $("#result").text("Failure");
        $("#webhook_id").text("");
        $("#errmsg").text(data.msg);
      }
      $("#process").text("Create Webhook").removeAttr("disabled");
    }).fail(function(jqXHR, textStatus, errorThrown) {
      $("#result").text("Error");
      $("#webhook_id").text("");
      switch(textStatus) {
        case "timeout":
          $("#errmsg").text("HTTP request timed out"); break;
        case "error":
          if(typeof errorThrown == "string" && errorThrown.length) {
            $("#errmsg").text("HTTP error: " + errorThrown);
          } else {
            $("#errmsg").text("HTTP error");
          }
          break;
        case "abort":
          $("#errmsg").text("HTTP request aborted"); break;
        case "parseerror":
          $("#errmsg").text("Parse error"); break;
      }
      $("#process").text("Create Webhook").removeAttr("disabled");
    });
  }).removeAttr("disabled");
});
