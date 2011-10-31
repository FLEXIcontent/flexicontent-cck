<%@ Page Language="VB" %>

<%@ Import Namespace="System.IO" %>

<script runat="server">
    Protected Sub Page_Load(ByVal sender As Object, ByVal e As System.EventArgs)
        If Not Request.Params("FilePath") = "" Then
            DownloadFile(Request.Params("FilePath"))
        Else
            Me.lblError.Text = "No file specified"
        End If
    End Sub
    
    Sub DownloadFile(ByVal strFileName As String)
        Dim strFileExt As String = Mid(strFileName, InStrRev(strFileName, ".") + 1)
        Dim strFullPath As String = Server.MapPath("~/" & strFileName)
        If New FileInfo(strFullPath).Exists Then
            Select Case strFileExt
                Case "vb", "lic", "asp", "asa", "aspx", "resx", "asax", "mdb", "ascx", "config", "fla", "dll", "js", "xml"
                    Me.lblError.Text = "You cannot download this file type"
                    Exit Sub
                Case ".asf"
                    Response.ContentType = "video/x-ms-asf"
                Case ".avi"
                    Response.ContentType = "video/avi"
                Case ".doc"
                    Response.ContentType = "application/msword"
                Case ".zip"
                    Response.ContentType = "application/zip"
                Case ".xls"
                    Response.ContentType = "application/vnd.ms-excel"
                Case ".gif"
                    Response.ContentType = "image/gif"
                Case ".png"
                    Response.ContentType = "image/png"
                Case ".jpg", "jpeg"
                    Response.ContentType = "image/jpeg"
                Case ".wav"
                    Response.ContentType = "audio/wav"
                Case ".mp3"
                    Response.ContentType = "audio/mpeg3"
                Case ".mpg", "mpeg"
                    Response.ContentType = "video/mpeg"
                Case ".rtf"
                    Response.ContentType = "application/rtf"
                Case ".htm", "html"
                    Response.ContentType = "text/html"
                Case ".pdf"
                    Response.ContentType = "application/pdf"
                Case ".ppt"
                    Response.ContentType = "application/mspowerpoint"
                Case Else
                    Response.ContentType = "application/octet-stream"
            End Select
            Try
                Trace.Warn(strFileExt)
                Response.ContentType = "application/" & strFileExt
                Response.AddHeader("content-disposition", "attachment;filename=""" & strFileName & """")
                Response.AddHeader("content-length", New FileInfo(strFullPath).Length)
                Response.WriteFile(strFullPath)
                Response.End()
            Catch EXC As Exception
                Me.lblError.Text = EXC.Message
            End Try
        Else
            Me.lblError.Text = "Selected file not found"
        End If
    End Sub
</script>

<asp:label ID="lblError" runat="server" />